<?php
include_once "config.php";

\ArrayDB\Database\Settings::setConnectionConfig(connectorSettings);

$db = new \ArrayDB\Database\Connector(table);

$listBefore = $db->select(["COUNT(*) AS `count`"]);
$truncate = $db->truncate() ? "Y" : "N";
$listAfter = $db->select(["COUNT(*) AS `count`"]);

echo "<b>Count before</b>: {$listBefore[0]['count']}";
echo "<br/><b>Truncated</b>: $truncate";
echo "<br/><b>Count after</b>: {$listAfter[0]['count']}";