<?php
include_once "config.php";

\ArrayDB\Database\Settings::setConnectionConfig(connectorSettings);

$db = new \ArrayDB\Database\Connector("new_table_test__" . floor(rand(0,999)));

$create = $db->createSchema() ? "Y" : "N";

echo "<b>Created</b>: $create";
echo "<br/><b>Table name</b>: {$db->getTable()}";