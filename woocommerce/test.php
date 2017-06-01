<?php echo 'adam';

try {
  echo '2';
  echo sqlsrv_connect('GOODPILL-SERVER', ['Database' => 'cph']);
  echo '3';
} catch($err) {
  echo '4';
  echo $err;
  echo '5';
} ?>
