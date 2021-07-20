<?php
require_once 'helpers/helper_try_catch_log.php';

use GoodPill\Logging\{
    GPLog,
    AuditLog,
    CliLog
};
use GoodPill\Utilities\Timer;
/**
 * Update all drug changes
 * @param  array $changes  An array of arrays with deledted, created, and
 *      updated elements
 * @return void
 */
function update_drugs(array $changes) : void
{

    // Make sure we have some data
    $change_counts = [];
    foreach (array_keys($changes) as $change_type) {
        $change_counts[$change_type] = count($changes[$change_type]);
    }

    if (array_sum($change_counts) == 0) {
        return;
    }

    GPLog::info(
        "update_drugs: changes",
        $change_counts
    );

    GPLog::notice('data-update-drugs', $changes);

    if (isset($changes['created'])) {
        Timer::start("update.drugs.created");
        foreach ($changes['created'] as $i => $created) {
            drug_created($created);
        }
        Timer::start("update.drugs.created");
    }

    if (isset($changes['updated'])) {
        Timer::start("update.drugs.updated");
        foreach ($changes['updated'] as $i => $updated) {
            drug_updated($updated);
        }
        Timer::start("update.drugs.updated");
    }
}

/*

    Change Handlers

 */

/**
 * Handle drug craeted
 * @param array $created The data for the created drug
 * @return void
 */
function drug_created(array $created) : void
{
    GPLog::$subroutine_id = "drugs-created-".sha1(serialize($created));
    GPLog::info("data-drugs-created", ['created' => $created]);
    GPLog::debug(
        "update_drugs: Drugs Created",
        [
          'updated' => $created,
          'source'  => 'v2',
          'type'    => 'drugs',
          'event'   => 'created'
        ]
    );

    $mysql = new Mysql_Wc();

    if ($created['drug_gsns']) {
        update_mismatched_rxs_and_items($mysql, $created);
        update_field_rxs_single($mysql, $created, 'drug_gsns'); //Now that everything is matched, we can update all rxs_single to the new gsn
    }

    GPLog::resetSubroutineId();
}
/**
 * Handle drug updated
 * @param array $updated The data for the updated drug
 * @return void
 */
function drug_updated(array $updated) : void
{
    GPLog::$subroutine_id = "drugs-updated-".sha1(serialize($updated));
    GPLog::info("data-drugs-updated", ['updated' => $updated]);
    GPLog::debug(
        "update_drugs: Drugs Updated",
        [
          'updated' => $updated,
          'source'  => 'v2',
          'type'    => 'drugs',
          'event'   => 'updated'
        ]
    );

    $mysql = new Mysql_Wc();

    if (
        $updated['price30'] != $updated['old_price30']
        || $updated['price90'] != $updated['old_price90']
    ) {
        $created = "Created:".date('Y-m-d H:i:s');
        $salesforce = [
            "subject"   => "Drug Price Change for $updated[drug_generic]",
            "body"      => "$updated[drug_generic] price $updated[old_price30] >>> $updated[price30], $updated[old_price90] >>> $updated[price90] $created",
            "assign_to" => ".Testing",
            "due_date"  => date('Y-m-d')
        ];
        $event_title = @$item['drug_name']." Drug Price Change $created";

        create_event($event_title, [$salesforce]);
    }

    if ($updated['drug_ordered'] && ! $updated['old_drug_ordered']) {
        GPLog::warning("new drug ordered", [ 'updated' => $updated ]);
    }

    if (! $updated['drug_ordered'] && $updated['old_drug_ordered']) {
        GPLog::warning("drug stopped being ordered", [ 'updated' => $updated ]);
    }

    if ($updated['drug_gsns'] != $updated['old_drug_gsns']) {
        update_mismatched_rxs_and_items($mysql, $updated);

        //Now that everything is matched, we can update all rxs_single to the new gsn
        update_field_rxs_single($mysql, $updated, 'drug_gsns');
    }

    if ($updated['drug_brand'] != $updated['old_drug_brand']) {
        update_field_rxs_single($mysql, $updated, 'drug_brand');
    }

    GPLog::resetSubroutineId();
}

/*

    Supporting Functions

 */

function update_field_rxs_single($mysql, $updated, $key)
{
    $sql = "
    UPDATE gp_rxs_single
    SET
      $key = '{$updated[$key]}'
    WHERE
      gp_rxs_single.drug_generic = '{$updated['drug_generic']}'
  ";

    $mysql->run($sql);
}

function update_mismatched_rxs_and_items($mysql, $partial)
{
     //update_drugs::created doesn't have old_drug_gsns
    $drug_gsns = @$partial['old_drug_gsns'] ?: $partial['drug_gsns'];

    // Strip all the  empty blanks off
    $drug_gsns = trim($drug_gsns, ',');

    $sql = "SELECT gp_order_items.*, gp_rxs_single.* -- specify order otherwise order_items.rx_number being null overwrites the rxs_single.rx_number
            FROM gp_rxs_single
            LEFT JOIN gp_order_items ON gp_rxs_single.rx_number = gp_order_items.rx_number
            WHERE
              NOT drug_generic <=> '{$partial['drug_generic']}' -- gsn was moved not just added
              AND rx_gsn IN ($drug_gsns)
              AND rx_dispensed_id IS NULL";

    $rxs = $mysql->run($sql)[0];

    if (!$rxs) {
        return GPLog::warning(
            "GSN UPDATE update_mismatched_rxs_and_items_by_drug_gsns: no rxs_single or order_items to update",
            [
              'partial' => $partial,
              'sql' => $sql,
              'rxs' => $rxs
            ]
        );
    }

    //NOTE Since we are here, GSNs moved in such a way that order_items might have:
    //pended for the wrong drug,
    //have the wrong price,
    //have the wrong initial stock level,
    //have the wrong days (due to the above), etc
    GPLog::warning(
        "update_mismatched_rxs_and_items_by_drug_gsns: updating rxs_single(s) and undispensed order_item(s)",
        [
            'partial' => $partial,
            'sql' => $sql,
            'rxs' => $rxs
        ]
    );

    foreach ($rxs as $rx) {
        update_rx_single_drug($mysql, $rx['rx_number']);
        update_order_item_drug($mysql, $rx['rx_number']);
    }
}

function update_rx_single_drug($mysql, $rx_number)
{

    if ( ! $rx_number) {
    	GPLog::error(
    		"update_drugs: update_rx_single_drug aborted because no rx_number passed",
    		[
    			'rx_number' => $rx_number
    		]
    	);

    	return;
    }

    $sql_rxs_single = "
    UPDATE gp_rxs_single
    JOIN gp_drugs ON
      gp_drugs.drug_gsns LIKE CONCAT('%,', rx_gsn, ',%')
    SET
      gp_rxs_single.drug_generic = gp_drugs.drug_generic,
      gp_rxs_single.drug_brand   = gp_drugs.drug_brand,
      gp_rxs_single.drug_gsns    = gp_drugs.drug_gsns
    WHERE
      gp_rxs_single.rx_number = '$rx_number'
    ";

    GPLog::warning("GSN UPDATE update_drugs: update_rx_single_drug (saving v2 drug names in gp_rxs_single)", [
        'sql_rxs_single'  => $sql_rxs_single,
        'rx_number'       => $rx_number
    ]);

    $mysql->run($sql_rxs_single);
}

function update_order_item_drug($mysql, $rx_number) {
    if ( ! $rx_number) {
        GPLog::error(
            "update_drugs: update_order_item_drug aborted because no rx_number passed",
            [
                'rx_number' => $rx_number
            ]
        );

        return;
    }

    /*
        Hacky but we want to FORCE $needs_repending = true in helper_full_fields
        so setting days to something unlikely to ever be correct

        Next load_full_patient/order/item should overwrite rx_messages,
        days_dispensed_default, and price_dispensed_default.  Should unpend and
        repend for the "new" drug
     */
    $sql_order_items = "UPDATE gp_order_items
                            SET
                              days_dispensed_default = 999
                            WHERE
                              gp_order_items.rx_number  = '{$rx_number}'
                              AND rx_dispensed_id IS NULL";

    GPLog::debug(
        "GSN UPDATE update_order_item_drug: updated gp_order_item BEFORE",
        [
            'sql_order_items' => $sql_order_items,
            'rx_number'       => $rx_number
         ]
    );

    $mysql->run($sql_order_items);

    //overwrite == true should force rx_messages, days_dispensed_default, and
    //price_dispensed_default to all be recalculated
    // TODO we will need to replace this as we migrate away from the load full fields.
    // TODO Instead we should get the order_item for the Drug and update it.  If there isn't
    // TODO an order item we should see if we need to add the rx to an order and sync it
    $item = load_full_item(['rx_number' => $rx_number], $mysql, true);

    GPLog::debug(
        "GSN UPDATE update_order_item_drug: updated gp_order_item AFTER",
        [
            'sql_order_items' => $sql_order_items,
            'rx_number'       => $rx_number,
            'item'           => $item
        ]
    );
}
