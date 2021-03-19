<?php

function getMock($name) {
  $data = @file_get_contents("testing/mocks/$name.json");

  if ($data) {
    return json_decode($data, true);
  } else {
    return [];
  }
}

function writeSQSToMock($name, $request) {
  //$file = fopen("testing/mocks/SQSRequests/$name.json", "w") or die("cannot open file $name");
  print_r($request);
  //fwrite($file, json_encode($request));
  //fclose($file);
}
