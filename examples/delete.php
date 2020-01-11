<?php
include_once "config.php";

\ArrayDB\Database\Settings::setConnectionConfig(connectorSettings);

$db = new \ArrayDB\Database\Connector(table);

$selectBefore = $db->setConditions(deleteConditions)->select();
$deleted = $db->setConditions(deleteConditions)->delete();
$selectAfter = $db->setConditions(deleteConditions)->select();

$deleted = $deleted ? "Y" : "N";

echo "<b>Deleted:</b> $deleted";
echo "<br/><b>Selection before deletion</b>:<pre>";
print_r($selectBefore);
echo "</pre><br/><b>Selection after deletion</b>:<pre>";
print_r($selectAfter);
echo "</pre>";