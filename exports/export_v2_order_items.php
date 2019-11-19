<?php

//TODO Pend v2 Inventory

function export_v2_add_pended($item) {
  log_info("
  export_v2_add_pended ");//.print_r($item, true);

  $vals = make_pick_list($item);
  print_pick_list($item, $vals);
  pend_pick_list($item, $vals);
}

function export_v2_remove_pended($item) {
  log_info("
  export_v2_remove_pended ");//.print_r($item, true);

  //delete_pick_list
  //$res = v2_fetch('/account/8889875187/pend/'.$item['invoice_number'], 'DELETE');

  //unpend_pick_list
  gdoc_remove_files(pick_list_prefix($item['invoice_number']), '1PcDYKM_Ky-9zWmCNuBnTka3uCKjU3A0q');
}

function pick_list_name($item) {
  return pick_list_prefix($item).pick_list_suffix($item);
}

function pick_list_prefix($item) {
  return 'Pick List #'.$item['invoice_number'].': ';
}

function pick_list_suffix($item) {
  return $item['drug_generic'];
}

function print_pick_list($item, $vals) {

  $header = [
    ['Order #'.$item['invoice_number'].' '.$item['drug_generic'].' '.$item['drug_name'].' '.date_create(), '', '' ,'', '', ''],
    ['Days:'.$item['days_dispensed_default'].', Qty:'.$item['qty_dispensed_default'].', Count:'.count($vals).(drug.$Stock ? ' ('+drug.$Stock+')' : '')+($shopped['half_fill'] || ''), '', '', '', '', ''],
    ['', '', '', '', '', '']
  ];

  $args = [
    'method'   => 'newSpreadsheet',
    'file'     => pick_list_name($item),
    'folder'   => PICK_LIST_FOLDER_NAME,
    'vals'     => $header + $vals,
    'widths'   => [1 => 243] //show the full id when it prints
  ];

  $result = gdoc_post(GD_MERGE_URL, $args);


  mail('adam@sirum.org', "WebForm make_pick_list", json_encode([$item, $args, $result]));
}

function pend_pick_list($item, $vals) {

  if ( ! LIVE_MODE) return mail('adam@sirum.org', "WebForm pend_pick_list", json_encode([$item, $vals]));

  //Pend after all forseeable errors are accounted for.
  //v2_fetch('/account/8889875187/pend/'.$item['invoice_number'].' - '.$item['qty_dispensed_default'], 'POST', $vals);
}

function make_pick_list($item) {

  $safety   = 0.15;
  $generic  = $item['drug_generic'];
  $min_days = $item['days_dispensed_default'];
  $min_qty  = $item['qty_dispensed_default'];
  $stock    = $item['stock_level'];

  $min_exp   = explode('-', date('Y-m', strtotime("+".($min_days-2*7)." days"))); //Used to use +14 days rather than -14 days as a buffer for dispensing and shipping. But since lots of prepacks expiring I am going to let almost expired things be prepacked
  $long_exp  = explode('-', date('Y-m', strtotime("+".($min_days+6*7)." days"))); //2015-05-13 We want any surplus from packing fast movers to be usable for ~6 weeks.  Otherwise a lot of prepacks expire on the shelf

  $start_key = '["8889875187","month","'.$min_exp[0].'","'.$min_exp[1].'","'.$generic.'"]';
  $end_key   = '["8889875187","month","'.$min_exp[0].'","'.$min_exp[1].'","'.$generic.'",{}]';

  $url  = '/transaction/_design/inventory.qty-by-generic/_view/inventory.qty-by-generic?reduce=false&include_docs=true&limit=300&startkey='.$start_key.'&endkey='.$end_key;
  $rows = v2_fetch($url);

  $unsorted_ndcs = group_by_ndc($rows, $item);
  $sorted_ndcs   = sort_by_ndc($unsorted_ndcs, $long_exp);
  $list          = get_qty_needed($sorted_ndcs, $min_qty, $safety);

  mail('adam@sirum.org', "Webform make_pick_list", json_encode([$url, $item, $list, $sorted_ndcs]));

  if ($list OR $min_days <= 45) return $list;

  mail('adam@sirum.org', "Webform Shopping Error: Not enough qty found, trying 45 days and no safety", json_encode([$url, $item, $list, $sorted_ndcs]);

  $list = get_qty_needed($sorted_ndcs, $min_qty*(45/$min_days*$min_qty), $safety);

  if ($list) {
    $list['half_fill'] = ', HALF FILL - COULD NOT FIND ENOUGH QUANTITY';
    return $list;
  }
}

function group_by_ndc($rows, $item) {
  //Organize by NDC since we don't want to mix them
  $ndcs = [];
  $caps = preg_match('/ cap(?!l)s?| cps?\b| softgel| sfgl\b/i', $item['drug_name']); //include cap, caps, capsule but not caplet which is more like a tablet
  $tabs = preg_match('/ tabs?| tbs?| capl\b/i', $item['drug_name']);  //include caplet which is like a tablet

  foreach ($rows as $row) {

    //Ignore Cindy's makeshift dispensed queue
    if (in_array($row['doc']['bin'], ['M00', 'T00', 'W00', 'R00', 'F00', 'X00', 'Y00', 'Z00']) continue;
    //Only select the correct form even though v2 gives us both
    if ($caps AND strpos('Tablet', $row['doc']['drug']['form']) !== false) {
      $msg = 'may only be available in capsule form';
      continue;
    }
    if ($tabs AND strpos('Capsule', $row['doc']['drug']['form']) !== false) {
      $msg = 'may only be available in tablet form';
      continue;
    }

    $ndc = $row['doc']['drug']['_id'];
    $ndcs[$ndc] = $ndcs[$ndc] ?: [];
    $ndcs[$ndc]['prepack_qty'] = $ndcs[$ndc]['prepack_qty'] ?: 0; //Hacky to set property on an array

    if (strlen($row['doc']['bin']) == 3) {
      $ndcs[$ndc]['prepack_qty'] += $row['doc']['qty']['to'];

      if ($row['doc']['exp']['to'] < $ndcs[$ndc]['prepack_exp'])
        $ndcs[$ndc]['prepack_exp'] = $row['doc']['exp']['to'];
    }

    $ndcs[$ndc][] = $row['doc'];
  }

  return $ndcs;
}

function sort_by_ndc($ndcs, $long_exp) {

  $sorted_ndcs = [];
  //Sort the highest prepack qty first
  foreach ($ndcs as $ndc => $row) {
    $sorted_ndcs[] = ['ndc' => $ndc, 'inventory' => sort_inventory($row, $long_exp)];
  }
  //Sort in descending order of prepack_qty. TODO should we look Exp date as well?
  usort($sorted_ndcs, function($a, $b) { return $b['inventory']['prepack_qty'] - $a['inventory']['prepack_qty'] });

  return $sorted_ndcs;
}

function sort_inventory($inventory, $long_exp) {

    //Lots of prepacks were expiring because pulled stock with long exp was being paired with short prepack exp making the surplus shortdated
    //Default to longExp since that simplifies sort() if there are no prepacks
    usort($inventory, function($a, $b) {

      //Deprioritize ones that are missing data
      if ( ! $b['bin'] OR ! $b['exp'] OR ! $b['qty']) return -1;
      if ( ! $a['bin'] OR ! $a['exp'] OR ! $a['qty']) return 1;

      //Priortize prepacks over other stock
      $aPack = strlen($a['bin']) == 3;
      $bPack = strlen($b['bin']) == 3;
      if ($aPack AND ! $bPack) return -1;
      if ($bPack AND ! $aPack) return 1;

      //Let's shop for non-prepacks that are closest (but not less than) to our min prepack exp date in order to avoid waste
      $aMonths = months_between($inventory['prepack_exp'] ?: $long_exp, substr($a['exp']['to'], 0, 10)); // >0 if minPrepackExp < a.doc.exp.to (which is what we prefer)
      $bMonths = months_between($inventory['prepack_exp'] ?: $long_exp, substr($b['exp']['to'], 0, 10)); // >0 if minPrepackExp < b.doc.exp.to (which is what we prefer)

      //Deprioritize anything with a closer exp date than the min prepack exp date.  This - by definition - can only be non-prepack stock
      if ($aMonths >= 0 AND $bMonths < 0) return -1;
      if ($bMonths >= 0 AND $aMonths < 0) return 1;

      //Priorize anything that is closer to - but not under - our min prepack exp
      //If there is no prepack this is set to 3 months out so that any surplus has time to sit on our shelf
      if ($aMonths >= 0 AND $bMonths >= 0 AND $aMonths < $bMonths) return -1;
      if ($aMonths >= 0 AND $bMonths >= 0 AND $bMonths < $aMonths) return 1;

      //If they both expire sooner than our min prepack exp pick the closest
      if ($aMonths < 0 AND $bMonths < 0 AND $aMonths > $bMonths) return -1;
      if ($aMonths < 0 AND $bMonths < 0 AND $bMonths > $aMonths) return 1;

      //When choosing between two items of same type and same exp, choose the one with a higher quantity (less items to shop for).
      if ($a['qty']['to'] > $b['qty']['to']) return -1;
      if ($b['qty']['to'] > $a['qty']['to']) return 1;

      //keep sorting the same as the view (ascending NDCs) [doc.drug._id, doc.exp.to || doc.exp.from, sortedBin, doc.bin, doc._id]
      return 0;
    });

    return $inventory;
}

function months_between($from, $to) {
  $diff = date_diff(date_create($from), date_create($to));
  return $diff->m + ($diff->y * 12);
}

function get_qty_needed($rows, $min_qty, $safety) {

  foreach ($rows as $row) {

    $ndc = $row['ndc'];
    $inventory = $row['inventory'];

    $list = [];
    $pend = [];
    $qty  = $min_qty;

    foreach ($inventory as $i => $option) {

      if ($i == 'prepack_qty') continue;

      array_unshift($pend, $option);

      $qty -= $pend[0]['qty']['to'] * (strlen($pend[0]['bin']) == 3 ? 1 : (1 - $safety));
      $list[] = [
        $pend[0]['_id'],
        $pend[0]['drug']['_id'],
        $pend[0]['drug']['form'],
        substr($pend[0]['exp']['to'], 0, 7),
        $pend[0]['qty']['to'],
        $pend[0]['bin']
      ];

      if ($qty <= 0) {
        return ['list' => usort($list, 'sort_list'), 'ndc' => $ndc, 'pend' => $pend ];
      }
    }
  }
}

function sort_list($a, $b) {

  $aBin = $a[4];
  $bBin = $b[4];

  $aPack = $aBin AND strlen($aBin) == 3;
  $bPack = $bBin AND strlen($bBin) == 3;

  if ($aPack > $bPack) return -1;
  if ($aPack < $bPack) return 1;

  //Flip columns and rows for sorting, since shopping is easier if you never move backwards
  $aFlip = $aBin[0].$aBin[2].$aBin[1].($aBin[3] ?: '');
  $bFlip = $bBin[0].$bBin[2].$bBin[1].($bBin[3] ?: '');

  if ($aFlip > $bFlip) return 1;
  if ($aFlip < $bFlip) return -1;$

  return 0;
}
