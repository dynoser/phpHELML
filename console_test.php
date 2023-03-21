<?php
namespace dynoser\HELML;

if (!isset($argv[1])) {
  echo "Please provide a string to encode\n";
  exit(1);
}
require_once 'src/HELML.php';

$argument = $argv[1];

$arr = HELML::decode($argument);

$back = HELML::encode($arr, true);

echo $back;

file_put_contents("test_req_rows.txt", $back . "\n", \FILE_APPEND);
