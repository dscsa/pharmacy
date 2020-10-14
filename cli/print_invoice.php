<?php

ini_set('memory_limit', '1024M');
ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'vendor/autoload.php';

//require_once 'exports/export_gd_orders.php';
//require_once 'dbs/mysql_wc.php';

$arrOptions   = getopt("i:hd", array());

if (!isset($arrOptions['i']) || isset($arrOptions['h'])) {
	echo "Usage: php print_invoice.php -i invoice_number\n";
	exit;
}

$invoice_number = $arrOptions['i'];
//mysql          = new Mysql_Wc();
// Grab the order details
// push the order over to the print
if (isset($arrOptions['d'])) {
  // Check for script running.
  $f = fopen('readme.md', 'w') or log_error('Cannot Create Lock File');

  while(!flock($f, LOCK_EX | LOCK_NB)) {
    echo "Waiting 5 seconds for lock\n";
    sleep(5);
  }

  echo "Removing Inovice and rebuilding\n";
  $mysql->run(
    "DELETE
        FROM gp_orders
        WHERE invoice_number = 46677"
  );

  update_orders_cp();

} else {
  $order = get_full_order(["invoice_number" => $invoice_number], $mysql);
  export_gd_publish_invoice($order, $mysql);
  export_gd_print_invoice($order);
  echo "Invoice {$invoice_number} queued to print";
}
