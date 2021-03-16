<?php

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CLiLog
};

use \GoodPill\DataModels\GoodPillOrder;
use \GoodPill\DataModels\GoodPillPendGroup;

require_once 'exports/export_cp_orders.php';

function export_v2_unpend_order($order, $mysql, $reason)
{
    GPLog::notice(
        "export_v2_unpend_order $reason " . @$order[0]['invoice_number'],
        ['order' => $order]
    );

    if (! @$order[0]['drug_name']) {
        GPLog::error(
            "export_v2_unpend_order: ABORTED! Order " . @$order[0]['invoice_number']
            . " doesn't seem to have any items. $reason",
            ['order' => $order]
        );
        return $order;
    }

    // Unpend the entire order
    v2_unpend_order_by_invoice($order[0]['invoice_number']);

    // save a blank picklist for each item
    foreach ($order as $i => $item) {
        $order[$i] = save_pick_list($item, 0, $mysql);
    }

    return $order;
}
/**
 * Pend an item in V2 for picking
 * @param  array $item   The item details to be picked
 * @param  Mysql_Wc $mysql  Mysql Object
 * @param  string $reason The reason we are pending the item
 * @return $item
 */
function v2_pend_item($item, $mysql, $reason)
{
    // Make sure there is an order before we Pend.  If there isn't one skip the
    // pend and put in an alert.
    $gp_order = new GoodPillOrder(['invoice_number' => $item['invoice_number']]);
    if (!$gp_order->loaded) {
        AuditLog::log(
            sprintf(
                "ABORTED PEND Attempted to pend %s for Rx#%s on Invoice #%s. This
                order doesn't exist in the Database",
                @$item['drug_name'],
                @$item['rx_number'],
                @$item['invoice_number']
            ),
            $item
        );

        GPLog::error(
            sprintf(
                "ABORTED PEND Attempted to pend %s for Rx#%s on Invoice #%s. This
                order doesn't exist in the Database",
                @$item['drug_name'],
                @$item['rx_number'],
                @$item['invoice_number']
            ),
            [ 'item' => $item ]
        );

        return $item;
    }

    // Abort the pend if we are missing a key field
    if (!$item['days_dispensed_default']
      or $item['rx_dispensed_id']
      or is_null($item['last_inventory'])
      or @$item['count_pended_total'] > 0) {
        AuditLog::log(
            sprintf(
                "ABORTED PEND Attempted to pend %s for Rx#%s on Invoice #%s for
                the following reasons: Missing days dispensed default - %s,
                The Rx hasn't been assigned - %s, There isn't inventory - %s,
                The number of doses is invalid - %s",
                @$item['drug_name'],
                @$item['rx_number'],
                @$item['invoice_number'],
                (!$item['days_dispensed_default']) ? 'Y':'N',
                ($item['rx_dispensed_id']) ? 'Y':'N',
                (is_null($item['last_inventory'])) ? 'Y':'N',
                (@$item['count_pended_total'] > 0) ? 'Y':'N'
            ),
            $item
        );

        GPLog::error(
            sprintf(
                "v2_pend_item: ABORTED! %s %s %s %s days_dispensed_default:%s
                rx_dispensed_id:%s last_inventory:%s count_pended_total:%s",
                @$item['invoice_number'],
                @$item['drug_name'],
                $reason,
                @$item['rx_number'],
                @$item['days_dispensed_default'],
                @$item['rx_dispensed_id'],
                @$item['last_inventory'],
                @$item['count_pended_total']
            ),
            [ 'item' => $item ]
        );
        return $item;
    }

    // Make the picklist
    $list = make_pick_list($item);

    if ($list) {
        AuditLog::log(
            sprintf(
                "Item %s for Rx#%s on Invoice #%s Pended because",
                @$item['drug_name'],
                @$item['rx_number'],
                @$item['invoice_number'],
                $reason
            ),
            $item
        );
        GPLog::debug(
            sprintf(
                "v2_pend_item: make_pick_list SUCCESS %s %s %s %s",
                $item['invoice_number'],
                $item['drug_name'],
                $reason,
                $item['rx_number']
            ),
            [
                'success' => true,
                'item' => $item,
                'list' => $list
            ]
        );
    } else {
        AuditLog::log(
            sprintf(
                "Item %s for Rx#%s on Invoice #%s FAILED to pend",
                @$item['drug_name'],
                @$item['rx_number'],
                @$item['invoice_number']
            ),
            $item
        );
        GPLog::error(
            sprintf(
                "v2_pend_item: make_pick_list ERROR %s %s %s %s",
                $item['invoice_number'],
                $item['drug_name'],
                $reason,
                $item['rx_number']
            ),
            [
              'success' => false,
              'item' => $item,
              'list' => $list
            ]
        );
    }

    print_pick_list($item, $list);
    pend_pick_list($item, $list);
    $item = save_pick_list($item, $list ?: 0, $mysql);
    return $item;
}

/**
 * UnPend(remove) an item in V2 for picking
 * @param  array $item   The item details to be picked
 * @param  Mysql_Wc $mysql  Mysql Object
 * @param  string $reason The reason we are pending the item
 * @return void
 */
function v2_unpend_item($item, $mysql, $reason)
{
    GPLog::notice(
        sprintf(
            "v2_unpend_item: Invoice: %s Drug: %s Reason: %s Rx# %s",
            @$item['invoice_number'],
            @$item['drug_name'],
            $reason,
            @$item['rx_number']
        ),
        [ 'item' => $item ]
    );

    if (! @$item['rx_number']) { //Empty order will just cause a mysql error in save_pick_list
        return $item;
    }

    AuditLog::log(
        sprintf(
            "Item %s for Rx#%s on Invoice #%s UN-Pended because %s",
            @$item['drug_name'],
            @$item['rx_number'],
            @$item['invoice_number'],
            $reason
        ),
        $item
    );

    if (!@$item['invoice_number']
        or !@$item['order_date_added']
        or !@$item['patient_date_added']) {
        AuditLog::log(
            sprintf(
                "Item %s for Rx#%s on Invoice #%s UN-Pended is missing:
                invoice number - %s, order date - %s, patient date - %s",
                @$item['drug_name'],
                @$item['rx_number'],
                @$item['invoice_number'],
                (!@$item['invoice_number']) ? 'Y':'N',
                (!@$item['order_date_added']) ? 'Y':'N',
                (!@$item['patient_date_added']) ? 'Y':'N'
            ),
            $item
        );
        GPLog::critical(
            "v2_unpend_item: NO INVOICE NUMBER, ORDER DATE ADDED, OR PATIENT DATE ADDED! "
                . @$item['invoice_number'] . " " . @$item['drug_name'] . " $reason "
                . @$item['rx_number'] . ". rx_dispensed_id:" . @$item['rx_dispensed_id']
                . " last_inventory:" . @$item['last_inventory'] . " count_pended_total:"
                . @$item['count_pended_total'],
            ['item' => $item]
        );
    }

    unpend_pick_list($item);
    $item = save_pick_list($item, 0, $mysql);
    return $item;
}

function save_pick_list($item, $list, $mysql)
{
    // Check to see if the items has an invoice number and a rx_number
    // If those are missing, we should thorw an alert and return
    if (empty($item['rx_number'])) {
        GPLog::critical(
            'Trying to save a picklist but th item is missing the rx_number',
            [ 'item' => $item ]
        );
        return $item;
    }

    if ($list === 0) {
        $list = [
          'qty'           => 0,
          'qty_repacks'   => 0,
          'count'         => 0,
          'count_repacks' => 0
        ];
    }

    if (! $list) {
        return $item;
    } //List could not be made

    $item['qty_pended_total']     = $list['qty'];
    $item['qty_pended_repacks']   = $list['qty_repacks'];
    $item['count_pended_total']   = $list['count'];
    $item['count_pended_repacks'] = $list['count_repacks'];

    $sql = "UPDATE
              gp_order_items
            SET
              qty_pended_total = {$list['qty']},
              qty_pended_repacks = {$list['qty_repacks']},
              count_pended_total = {$list['count']},
              count_pended_repacks = {$list['count_repacks']}
            WHERE
              invoice_number = {$item['invoice_number']} AND
              rx_number = {$item['rx_number']}";

    GPLog::notice(
        "save_pick_list: $item[invoice_number] " . @$item['drug_name'] . " "
            . @$item['rx_number'],
        [ 'item' => $item, 'list' => $list, 'sql' => $sql ]
    );

    $mysql->run($sql);

    export_cp_set_expected_by($item);

    return $item;
}

function pick_list_name($item)
{
    return pick_list_prefix($item).pick_list_suffix($item);
}

function pick_list_prefix($item)
{
    return 'Pick List #'.$item['invoice_number'].': ';
}

function pick_list_suffix($item)
{
    return $item['drug_generic'];
}

function print_pick_list($item, $list)
{
    $pend_group_name = pend_group_name($item);

    if (! $list) {
        return;
    } //List could not be made

    $header = [
        [
            "Pick List: Order #$pend_group_name $item[drug_generic] ($item[drug_name])",
            '',
            '' ,
            '',
            '',
            ''
        ],
        [
            "Rx $item[rx_number]. $item[rx_message_key]. Item Added:$item[item_date_added]. Created ".date('Y-m-d H:i:s'),
            '',
            '' ,
            '',
            '',
            ''
        ],
        [
            $list['partial_fill']."Count:$list[count], Days:$item[days_dispensed_default], Qty:$item[qty_dispensed_default] ($list[qty]), Stock:$item[stock_level_initial], ",
            '',
            '',
            '',
            '',
            ''
        ],
        [
            '',
            '',
            '',
            '',
            '',
            ''
        ],
        [
            'id',
            'ndc',
            'form',
            'exp',
            'qty',
            'bin'
        ]
      ];

    $args = [
        'method'   => 'newSpreadsheet',
        'file'     => pick_list_name($item),
        'folder'   => PICK_LIST_FOLDER_NAME,
        'vals'     => array_merge($header, $list['list']), //merge arrays, make sure array is not associative or json will turn to object
        'widths'   => [1 => 243] //show the full id when it prints
    ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    GPLog::notice(
        "print_pick_list: $item[invoice_number] ".@$item['drug_name']." ".@$item['rx_number'],
        [
            'item' => $item,
            'count list' => count($list['list']),
            'count pend' => count($list['pend'])
        ]
    ); //We don't need full shopping list cluttering logs
}


/**
 * Check to see if this specific item has already been pended
 * @param  array   $item           The data for an order item
 * @param  boolean $include_picked (Optional) Should we check for pended and picked
 * @return boolean       False if the item is not pended in v2
 */
function get_item_pended_group($item, $include_picked = false)
{
    $possible_pend_groups = [
        'expected'        => pend_group_name($item),
        'refill'          => pend_group_refill($item),
        'webform'         => pend_group_webform($item),
        'new_patient'     => pend_group_new_patient($item),
        'new_patient_old' => pend_group_new_patient_old($item),
        'manual'          => pend_group_manual($item)
    ];

    foreach ($possible_pend_groups as $type => $group) {
        $pend_url = "/account/8889875187/pend/{$group}/{$item['drug_generic']}";
        $results  = v2_fetch($pend_url, 'GET');
        if (!empty($results) &&
            @$results[0]['next'][0]['pended']) {
            if ($type != 'expected') {
                GPLog::debug(
                    'Drugs pended under unexpected pend group',
                    [
                        'expected' => $possible_pend_groups['expected'],
                        'found_as' => $group
                    ]
                );
            }

            return $group;
        }
    }

    return false;
}

/*
 * Pend Group Name functions
 */

/**
 * Get the pend group name for a Refill order
 * @param  array $item  The item data
 * @return string
 */
function pend_group_refill($item)
{
    $pick_time = strtotime($item['order_date_added'].' +2 days'); //Used to be +3 days
    $invoice   = "R{$item['invoice_number']}"; //N < R so new scripts will appear first on shopping list
    $pick_date = date('Y-m-d', $pick_time);
    return "$pick_date $invoice";
}

function pend_group_webform($item)
{
    $pick_time = strtotime($item['order_date_added'].' +0 days'); //Used to be +1 days
    $invoice   = "W{$item['invoice_number']}";
    $pick_date = date('Y-m-d', $pick_time);
    return "$pick_date $invoice";
}

function pend_group_new_patient($item)
{
    $pick_time = strtotime(@$item['patient_date_added'].' -8 days');
    $invoice   = "P{$item['invoice_number']}";
    $pick_date = date('Y-m-d', $pick_time);
    return "$pick_date $invoice";
}

//This can be deleted once 2021-01-12 P55855 is dispensed
function pend_group_new_patient_old($item)
{
    $pick_time = strtotime($item['patient_date_added'].' +0 days');
    $invoice   = "P{$item['invoice_number']}";
    $pick_date = date('Y-m-d', $pick_time);
    return "$pick_date $invoice";
}

function pend_group_manual($item)
{
    return $item['invoice_number'];
}

function pend_group_name($item)
{

    // See if there is already a pend group for this order
    $pend_group = new GoodPillPendGroup(
        ['invoice_number' => $item['invoice_number']]
    );

    if ($pend_group->loaded) {
        return $pend_group->pend_group;
    }

    //TODO need a different flag here because "Auto Refill v2" can be overwritten by "Webform XXX"
    //We need a flag that won't change otherwise items can be pended under different pending groups
    //Probably need to have each "app" be a different "CP user" so that we can look at item_added_by
    if (is_auto_refill($item)) {
        $pend_group_name = pend_group_refill($item);
    } elseif (!isset($pend_group_name) && @$item['refills_used'] > 0) {
        $pend_group_name = pend_group_webform($item);
    } else {
        $pend_group_name = pend_group_new_patient($item);
    }

    $pend_group->invoice_number = $item['invoice_number'];
    $pend_group->pend_group = $pend_group_name;
    $pend_group->create();

    return $pend_group_name;
}

/**
 * Send a picklist to v2 so it is pended
 * @param  array  $item An item to pend
 * @param  array  $list A v2 compatible picklist
 * @return boolean      False if the list was already pended or was not there.
 */
function pend_pick_list($item, $list)
{
    if (!$list) {
        return false;
    } //List could not be made

    if ($pended_group = get_item_pended_group($item)) {
        // If we get a list of pendgroups, we should compare them to what we
        // are trying to pend and if the new pendgroup is different, unpend them
        // then repend them with the proper pend group
        AuditLog::log(
            sprintf(
                "ABORTED PEND! %s for %s appears to be already pended in pend group %s.  Please confirm.",
                @$item['drug_name'],
                @$item['invoice_number'],
                $pended_group
            ),
            $item
        );

        GPLog::notice(
            sprintf(
                "v2_pend_item: ABORTED! %s for %s appears to be already pended in pend group %s.  Please confirm.",
                @$item['drug_name'],
                @$item['invoice_number'],
                $pended_group
            ),
            [ 'item' => $item ]
        );

        CliLog::notice(
            sprintf(
                "ABORTED PEND! %s for %s appears to be already pended in pend group %s",
                @$item['drug_name'],
                @$item['invoice_number'],
                $pended_group
            )
        );

        return false;
    }

    // TODO PEND
    $pend_group_name = pend_group_name($item);
    $qty             = round($item['qty_dispensed_default']);

    CliLog::debug("pending item {$pend_group_name}:{$item['drug_generic']} - $qty");

    $pend_url = "/account/8889875187/pend/$pend_group_name?repackQty=$qty";

    //Pend after all forseeable errors are accounted for.
    $res = v2_fetch($pend_url, 'POST', $list['pend']);

    if (isset($res) && $list['pend'][0]['_rev'] != $res[0]['rev']) {
        GPLog::debug("pend_pick_list: SUCCESS!! {$item['invoice_number']} {$item['drug_name']} {$item['rx_number']}");
        return true;
    }

    AuditLog::log(
        sprintf(
            "PEND Failed %s for %s failed to pend.   %s.  Please manually pend if needed.",
            @$item['drug_name'],
            @$item['invoice_number'],
            $pended_group
        ),
        $item
    );

    GPLog::warning("pend_pick_list: FAILURE!! {$item['invoice_number']} {$item['drug_name']} {$item['rx_number']}");
    return false;
}

/**
 * Remove a specific picklist.  Picklist is the smallest object and this function
 * Actually makes the call to unpend the item from v2
 * @param  array $item        The item we are trying to remove
 * @param  array  $pendgroups (Optional) a specific list of pend groups to
 *      remove the item from
 * @return [type]             [description]
 */
function unpend_pick_list($item)
{

    // TODO PEND
    //
    // If we don't have specific pendgroups, then go get some
    $pend_group = get_item_pended_group($item);

    if (!$pend_group) {
        GPLog::warning(
            sprintf(
                "v2_unpend_item: Nothing Unpened.  Call could have been avoided! %s %s %s",
                @$item['invoice_number'],
                @$item['drug_name'],
                @$item['rx_number']
            ),
            ['item' => $item]
        );
        CliLog::notice(
            sprintf(
                "Nothing Unpened  %s %s %s",
                @$item['invoice_number'],
                @$item['drug_name'],
                @$item['rx_number']
            ),
            ['invoice_number' => @$item['invoice_number']]
        );
    } else {
        CliLog::info(
            sprintf(
                "unpending item %s in %s",
                $item['drug_generic'],
                $pend_group
            )
        );
        do { // Keep doing until we can't find a pended items
            $loop_count = (isset($loop_count) ? ++$loop_count : 1);
            if ($results = v2_fetch("/account/8889875187/pend/{$pend_group}/{$item['drug_generic']}", 'DELETE')) {
                CLiLog::info(
                    sprintf(
                        "succesfully unpended item %s in %s, unpend attempt #%s",
                        $item['drug_generic'],
                        $pend_group,
                        $loop_count
                    )
                );
                GPLog::info(
                    sprintf(
                        "succesfully unpended item %s in %s, unpend attempt #%s",
                        $item['drug_generic'],
                        $pend_group,
                        $loop_count
                    ),
                    ['invoice_number' => $item['invoice_number']]
                );
                break;
            }
        } while (($pend_group = get_item_pended_group($item)) && $loop_count <= 5);
    }

    //Delete gdoc pick list
    $args = [
        'method'   => 'removeFiles',
        'file'     => pick_list_prefix($item),
        'folder'   => PICK_LIST_FOLDER_NAME
    ];

    $result = gdoc_post(GD_HELPER_URL, $args);

    GPLog::notice("unpend_pick_list", get_defined_vars());
}

//Getting all inventory of a drug can be thousands of items.  Let's start with a low limit that we increase as needed
function make_pick_list($item, $limit = 500)
{
    if (! isset($item['stock_level_initial']) and $item['rx_gsn']) { //If missing GSN then stock level won't be set
        GPLog::error("ERROR make_pick_list: stock_level_initial is not set", get_defined_vars());
    }

    $safety   = 0.05; //Percent qty to overshop
    $min_qty  = $item['qty_dispensed_default'];

    // 2015-05-13 We want any surplus from packing fast movers to be usable for
    // ~6 weeks.  Otherwise a lot of prepacks expire on the shelf
    $long_exp = date('Y-m-01', strtotime("+".($item['days_dispensed_default']+6*7)." days"));

    $inventory     = get_v2_inventory($item, $limit);
    $unsorted_ndcs = group_by_ndc($inventory, $item);
    $sorted_ndcs   = sort_by_ndc($unsorted_ndcs, $long_exp);
    $list          = get_qty_needed($sorted_ndcs, $min_qty, $safety);

    GPLog::notice(
        "make_pick_list: $item[invoice_number] " .@ $item['drug_name']
            . " " . @$item['rx_number'],
        [ 'item' => $item ]
    ); //We don't need full shopping list cluttering logs

    if ($list) {
        $list['partial_fill'] = '';
        return $list;
    }

    if (count($inventory) == $limit) {  //We didn't make the list but there are more drugs that we can scan
        GPLog::error(
            "Webform Pending Error: Not enough qty found for {$item['drug_generic']}."
                . " Increasing limit from {$limit} to " . ($limit*2),
            [ 'count_inventory' => count($inventory), 'item' => $item]
        );
        return make_pick_list($item, $limit*2);
    }

    if ($item['stock_level'] != "OUT OF STOCK") {
        GPLog::error(
            "Webform Pending Error: Not enough qty found for {$item['drug_generic']} "
                . "although it's not OUT OF STOCK.  Looking for {$min_qty} with "
                . "last_inventory of {$item['last_inventory']} (limit {$limit}) #1 of 3, "
                . "trying half fill and no safety",
            [ 'count_inventory' => count($inventory), 'item' => $item ]
        );
    }

    $list = get_qty_needed($sorted_ndcs, $min_qty*0.5, 0);

    if ($list) {
        $list['partial_fill'] = 'HALF FILL - COULD NOT FIND ENOUGH QUANTITY, ';
        return $list;
    }

    GPLog::error(
        "Webform Pending Error: Not enough qty found for {$item['drug_generic']}. "
            . "Looking for {$min_qty} with last_inventory of {$item['last_inventory']} "
            . " (limit {$limit}) #2 of 3, half fill with no safety failed",
        [
            'inventory'       => $inventory,
            'sorted_ndcs'     => $sorted_ndcs,
            'count_inventory' => count($sorted_ndcs),
            'item'            => $item
        ]
    );

    $thirty_day_qty = $min_qty/$item['days_dispensed_default']*30;
    $list = get_qty_needed($sorted_ndcs, $thirty_day_qty, 0);

    if ($list) {
        $list['partial_fill'] = '30 DAY FILL - COULD NOT FIND ENOUGH QUANTITY, ';
        return $list;
    }

    GPLog::error(
        "Webform Pending Error: Not enough qty found for {$item['drug_generic']}."
            . " Looking for {$min_qty} with last_inventory of {$item['last_inventory']} "
            . " (limit $limit) #3 of 3, 30 day with no safety failed",
        [
            'inventory' => $inventory,
            'sorted_ndcs' => $sorted_ndcs,
            'count_inventory' => count($sorted_ndcs),
            'item' => $item
        ]
    );

    //otherwise could create upto 3 SF tasks. rxs-single-updated, orders-cp-created, sync-to-date
    if (! is_null($item['count_pended_total'])) {
        return;
    }

    $created = "Created:".date('Y-m-d H:i:s');

    $salesforce = [
        "subject"   => "Order #$item[invoice_number] cannot pend enough $item[drug_name]",
        "body"      => "Determine if there is enough $item[drug_name] to pend for "
                       ."'{$item['sig_actual']}'. Tried & failed to pend a qty of ".$min_qty." $created",
        "contact"   => "$item[first_name] $item[last_name] $item[birth_date]",
        "assign_to" => ".Add/Remove Drug - RPh",
        "due_date"  => date('Y-m-d')
    ];

    $event_title = "$item[invoice_number] Pending Error: $salesforce[contact] $created";

    create_event($event_title, [$salesforce]);
}

function get_v2_inventory($item, $limit)
{
    $generic  = $item['drug_generic'];
    $min_days = $item['days_dispensed_default'];
    $stock    = $item['stock_level_initial'];

    //Used to use +14 days rather than -14 days as a buffer for dispensing and shipping.
  //But since lots of prepacks expiring I am going to let almost expired things be prepacked.
  //Update on 2020-12-03, -14 days is causing issues when we are behind on filling (on 12/1/2020 a 90 day Rx was pended for exp 01/2021)
  $days_adjustment = 0; //-14 //+14
  $min_exp   = explode('-', date('Y-m', strtotime("+".($min_days+$days_adjustment)." days")));

    $start_key = rawurlencode('["8889875187","month","'.$min_exp[0].'","'.$min_exp[1].'","'.$generic.'"]');
    $end_key   = rawurlencode('["8889875187","month","'.$min_exp[0].'","'.$min_exp[1].'","'.$generic.'",{}]');

    $url  = "/transaction/_design/inventory-by-generic/_view/inventory-by-generic?reduce=false&include_docs=true&limit=$limit&startkey=$start_key&endkey=$end_key";

    try {
        $res = v2_fetch($url);
        log_info("WebForm make_pick_list fetch success.", ['url' => $url, 'item' => $item, 'res' => $res]);
    } catch (Error $e) {
        GPLog::error("WebForm make_pick_list fetch failed.  Retrying $item[invoice_number]", ['url' => $url, 'item' => $item, 'res' => $res, 'error' => $e]);
        $res = v2_fetch($url);
    }

    return $res['rows'];
}

function group_by_ndc($rows, $item)
{
    //Organize by NDC since we don't want to mix them
    $ndcs = [];
    $caps = preg_match('/ cap(?!l)s?| cps?\\b| softgel| sfgl\\b/i', $item['drug_name']); //include cap, caps, capsule but not caplet which is more like a tablet
    $tabs = preg_match('/ tabs?| tbs?| capl\\b/i', $item['drug_name']);  //include caplet which is like a tablet

    foreach ($rows as $row) {
        if (
            isset($row['doc']['next'][0])
            && (
                 count($row['doc']['next'][0]) > 1
                 || (
                        count($row['doc']['next'][0]) == 1
                        && ! isset($row['doc']['next'][0]['picked'])
                )
            )
        ) {
            GPLog::error('Shopping list pulled inventory in which "next" is set!', $row, $item);
            if (! empty($row['doc']['next']['dispensed'])) {
                continue;
            }
        }

        //Ignore Cindy's makeshift dispensed queue
        if (in_array($row['doc']['bin'], ['M00', 'T00', 'W00', 'R00', 'F00', 'X00', 'Y00', 'Z00'])) {
            continue;
        }
        //Only select the correct form even though v2 gives us both
        if ($caps and stripos($row['doc']['drug']['form'], 'Tablet') !== false) {
            $msg = 'may only be available in capsule form';
            continue;
        }
        if ($tabs and stripos($row['doc']['drug']['form'], 'Capsule') !== false) {
            $msg = 'may only be available in tablet form';
            continue;
        }

        $ndc = $row['doc']['drug']['_id'];
        $ndcs[$ndc] = isset($ndcs[$ndc]) ? $ndcs[$ndc] : [];
        $ndcs[$ndc]['rows'] = isset($ndcs[$ndc]['rows']) ? $ndcs[$ndc]['rows'] : [];
        $ndcs[$ndc]['prepack_qty'] = isset($ndcs[$ndc]['prepack_qty']) ? $ndcs[$ndc]['prepack_qty'] : 0; //Hacky to set property on an array

        if (strlen($row['doc']['bin']) == 3) {
            $ndcs[$ndc]['prepack_qty'] += $row['doc']['qty']['to'];

            if (! isset($ndcs[$ndc]['prepack_exp']) or $row['doc']['exp']['to'] < $ndcs[$ndc]['prepack_exp']) {
                $ndcs[$ndc]['prepack_exp'] = $row['doc']['exp']['to'];
            }
        }

        $ndcs[$ndc]['rows'][] = $row['doc'];
    }

    return $ndcs;
}

function sort_by_ndc($ndcs, $long_exp)
{
    $sorted_ndcs = [];
    //Sort the highest prepack qty first
    foreach ($ndcs as $ndc => $val) {
        $sorted_ndcs[] = [
            'ndc'         => $ndc,
            'prepack_qty' => $val['prepack_qty'],
            'inventory'   => sort_inventory($val['rows'], $long_exp)
        ];
    }
    //Sort in descending order of prepack_qty with Exp limits.  If Exp is not included
    //then purchased stock gets used before anything else
    usort($sorted_ndcs, function ($a, $b) use ($sorted_ndcs) {
        if (! isset($a['prepack_qty']) or ! isset($b['prepack_qty'])) {
            GPLog::error('ERROR: sort_by_ndc but prepack_qty is not set', get_defined_vars());
        } else {
        //Return shortest prepack expiration date, if a tie use one with greater quantity
            if (@$a['prepack_exp'] > @$b['prepack_exp']) {
                return 1;
            }

            if (@$a['prepack_exp'] < @$b['prepack_exp']) {
                return -1;
            }

            return $b['prepack_qty'] - $a['prepack_qty'];
        }
    });

    return $sorted_ndcs;
}

function sort_inventory($inventory, $long_exp)
{

    // Lots of prepacks were expiring because pulled stock with long exp was being
    // paired with short prepack exp making the surplus shortdated
    // Default to longExp since that simplifies sort() if there are no prepacks
    usort($inventory, function ($a, $b) use ($inventory, $long_exp) {

      //Deprioritize ones that are missing data
        if (! $b['bin'] or ! $b['exp'] or ! $b['qty']) {
            return -1;
        }
        if (! $a['bin'] or ! $a['exp'] or ! $a['qty']) {
            return 1;
        }

        //Priortize prepacks over other stock
        $aPack = strlen($a['bin']) == 3;
        $bPack = strlen($b['bin']) == 3;
        if ($aPack and ! $bPack) {
            return -1;
        }
        if ($bPack and ! $aPack) {
            return 1;
        }

        // Let's shop for non-prepacks that are closest (but not less than) to
        // our min prepack exp date in order to avoid waste

        // >0 if minPrepackExp < a.doc.exp.to (which is what we prefer)
        $aMonths = months_between(
            (isset($inventory['prepack_exp']) ? $inventory['prepack_exp'] : $long_exp),
            substr($a['exp']['to'], 0, 10)
        );

        // >0 if minPrepackExp < b.doc.exp.to (which is what we prefer)
        $bMonths = months_between(
            (isset($inventory['prepack_exp']) ? $inventory['prepack_exp'] : $long_exp),
            substr($b['exp']['to'], 0, 10)
        );

        // Deprioritize anything with a closer exp date than the min prepack exp
        // date.  This - by definition - can only be non-prepack stock
        if ($aMonths >= 0 and $bMonths < 0) {
            return -1;
        }

        if ($bMonths >= 0 and $aMonths < 0) {
            return 1;
        }

        // Priorize anything that is closer to - but not under - our min prepack exp
        // If there is no prepack this is set to 3 months out so that any surplus
        // has time to sit on our shelf
        if ($aMonths >= 0 and $bMonths >= 0 and $aMonths < $bMonths) {
            return -1;
        }
        if ($aMonths >= 0 and $bMonths >= 0 and $bMonths < $aMonths) {
            return 1;
        }

        //If they both expire sooner than our min prepack exp pick the closest
        if ($aMonths < 0 and $bMonths < 0 and $aMonths > $bMonths) {
            return -1;
        }
        if ($aMonths < 0 and $bMonths < 0 and $bMonths > $aMonths) {
            return 1;
        }

        // keep sorting the same as the view (ascending NDCs) [doc.drug._id,
        // doc.exp.to || doc.exp.from, sortedBin, doc.bin, doc._id]
        return 0;
    });

    return $inventory;
}

function months_between($from, $to)
{
    $diff = date_diff(date_create($from), date_create($to));
    return $diff->m + ($diff->y * 12);
}

function get_qty_needed($rows, $min_qty, $safety)
{

    foreach ($rows as $row) {
        $ndc = $row['ndc'];
        $inventory = $row['inventory'];

        $list          = [];
        $pend          = [];
        $qty           = 0;
        $qty_repacks   = 0;
        $count_repacks = 0;
        $left          = $min_qty;
        $max_qty       = ($left * 1.25);

        foreach ($inventory as $i => $option) {
            if ($i == 'prepack_qty') {
                continue;
            }

            array_unshift($pend, $option);

            $usable = 1 - $safety;
            if (strlen($pend[0]['bin']) == 3) {
                $usable = 1;
                $qty_repacks += $pend[0]['qty']['to'];
                $count_repacks++;
            }

            $qty += $pend[0]['qty']['to'];
            $left -= $pend[0]['qty']['to'] * $usable;
            $list = pend_to_list($list, $pend);

            /*
                Shop for all matching medicine in the bin, its annoying and inefficient to pick some
                 and leave the others

                Update 1: Don't do the above if we are in a prepack bin, otherwise way will way
                overshop (eg Order #42107)
                Update 2: Don't do if they are manufacturer bottles otherwise we get way too much
                Update 3: Manufacturer bottles are anything over 60
                Update 4: Quit Pending if we are over 50%% of the originaly pend request
            */
            $different_bin = ($pend[0]['bin'] != @$inventory[$i+1]['bin']);
            $is_prepack    = (strlen($pend[0]['bin']) == 3);
            $is_mfg_bottle = ($pend[0]['qty']['to'] >= 60);
            $over_max      = $qty > $max_qty;

            if (
                $left <= 0
                and (
                    $over_max
                    or $different_bin
                    or $is_prepack
                    or $is_mfg_bottle
                )
            ) {
                usort($list, 'sort_list');
                return [
                    'list'          => $list,
                    'ndc'           => $ndc,
                    'pend'          => $pend,
                    'qty'           => $qty,
                    'count'         => count($list),
                    'qty_repacks'   => $qty_repacks,
                    'count_repacks' => $count_repacks
                ];
            }
        }
    }
}

function pend_to_list($list, $pend)
{
    $list[] = [
    $pend[0]['_id'],
    $pend[0]['drug']['_id'],
    $pend[0]['drug']['form'],
    substr($pend[0]['exp']['to'], 0, 7),
    $pend[0]['qty']['to'],
    $pend[0]['bin']
  ];
    return $list;
}

function sort_list($a, $b)
{
    $aBin = $a[5];
    $bBin = $b[5];

    $aPack = $aBin and strlen($aBin) == 3;
    $bPack = $bBin and strlen($bBin) == 3;

    if ($aPack > $bPack) {
        return 1;
    }
    if ($aPack < $bPack) {
        return -1;
    }

    //Flip columns and rows for sorting, since shopping is easier if you never move backwards
    $aFlip = $aBin[0].$aBin[2].$aBin[1].(@$aBin[3] ?: '');
    $bFlip = $bBin[0].$bBin[2].$bBin[1].(@$bBin[3] ?: '');

    if ($aFlip < $bFlip) {
        return 1;
    }
    if ($aFlip > $bFlip) {
        return -1;
    }

    return 0;
}
