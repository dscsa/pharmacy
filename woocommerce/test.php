<?php echo 'start';

  $conn = sqlsrv_connect('GOODPILL-SERVER', ['Database' => 'cph']);
  if( $conn === false ) {
       die( print_r( sqlsrv_errors(), true));
  }

  $sql = "select * from cppat";

  $stmt = sqlsrv_query( $conn, $sql);
  if( $stmt === false ) {
       die( print_r( sqlsrv_errors(), true));
  }

  print_r($stmt);
  
  echo 'end';
?>
