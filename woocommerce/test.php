<?php echo 'adam';

try {
  echo sqlsrv_connect('GOODPILL-SERVER', ['Database' => 'cph']);
} catch($err) {
  echo $err;
} ?>
