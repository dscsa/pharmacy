<?php

require_once 'imports/import_cp_patients.php';
require_once 'imports/import_cp_rxs_single.php';
require_once 'imports/import_cp_orders.php';
require_once 'imports/import_cp_order_items.php';

require_once 'updates/update_patients.php';
require_once 'updates/update_rxs_single.php';
require_once 'updates/update_orders.php';
require_once 'updates/update_order_items.php';

date_default_timezone_set('America/New_York');

$start = microtime(true);

import_cp_patients();
echo 'import_cp_patients '.(microtime(true) - $start);

import_cp_rxs_single();
echo 'import_cp_rxs_single '.(microtime(true) - $start);

import_cp_orders();
echo 'import_cp_orders '.(microtime(true) - $start);

//import_cp_order_items();

update_patients();
echo 'update_patients '.(microtime(true) - $start);

update_rxs_single();
echo 'update_rxs_single '.(microtime(true) - $start);

update_orders();
echo 'update_orders '.(microtime(true) - $start);

//update_order_items();
