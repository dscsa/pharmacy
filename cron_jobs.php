<?php
require './imports/import_grx_patients';
require './imports/import_grx_rxs_single';
require './imports/import_grx_orders';
require './imports/import_grx_order_items';

require './updates/update_patients';
require './updates/update_rxs_single';
require './updates/update_orders';
require './updates/update_order_items';

import_grx_patients();
import_grx_rxs_single();
import_grx_orders();
import_grx_order_items();

update_patients();
update_rxs_single();
update_orders();
update_order_items();
