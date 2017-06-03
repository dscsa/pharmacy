<?php echo 'start';

  $conn = sqlsrv_connect('GOODPILL-SERVER', ['Database' => 'cph']);
  if( $conn === false ) {
       die( print_r( sqlsrv_errors(), true));
  }

  $sql = "select * from cppat";

  $query = sqlsrv_query( $conn, $sql);
  if( $query === false ) {
       die( print_r( sqlsrv_errors(), true));
  }

  print_r($query);

  while( $row = sqlsrv_fetch_array( $query, SQLSRV_FETCH_ASSOC )) {
    print_r($row);
  }
  
  echo 'end';
?>
