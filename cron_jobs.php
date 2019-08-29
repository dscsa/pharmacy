<?php
require_once 'imports/import_grx_patients.php';
require_once 'imports/import_grx_rxs_single.php';
require_once 'imports/import_grx_orders.php';
require_once 'imports/import_grx_order_items.php';

require_once 'updates/update_patients.php';
require_once 'updates/update_rxs_single.php';
require_once 'updates/update_orders.php';
require_once 'updates/update_order_items.php';

//import_grx_patients();
//import_grx_rxs_single();
import_grx_orders();
//import_grx_order_items();

//update_patients();
//update_rxs_single();
update_orders();
//update_order_items();
