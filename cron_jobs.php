<?php
require './imports/import_grx_patients.php';
require './imports/import_grx_rxs_single.php';
require './imports/import_grx_orders.php';
require './imports/import_grx_order_items.php';

require './updates/update_patients.php';
require './updates/update_rxs_single.php';
require './updates/update_orders.php';
require './updates/update_order_items.php';

import_grx_patients();
import_grx_rxs_single();
import_grx_orders();
import_grx_order_items();

update_patients();
update_rxs_single();
update_orders();
update_order_items();
