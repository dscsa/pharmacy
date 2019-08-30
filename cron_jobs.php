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

import_cp_patients();
//import_cp_rxs_single();
import_cp_orders();
//import_cp_order_items();

update_patients();
//update_rxs_single();
update_orders();
//update_order_items();
