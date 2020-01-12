<?php
include_once "config.php";

\ArrayDB\Database\Settings::setConnectionConfig(connectorSettings);

$db = new \ArrayDB\Database\Connector(table);

$selectBeforeUpdate = $selectAfterUpdate = [];
$primaryValue = 6;

$selectConditions = [
    primaryKeyColumn => $primaryValue,
];
$selectBeforeUpdate = $db->setConditions($selectConditions)->select();

$updateFields = updateFields;
// different from save, the UPDATE method can't use primaryKey on fields, just on conditions
// so instead of changin it's value we will unset it:
unset($updateFields[primaryKeyColumn]);
$updated = $db->setFields($updateFields)->setConditions($selectConditions)->update();

if($updated) {
    $selectAfterUpdate = $db->setConditions($selectConditions)->select();
}

$updated = $updated ? "Y" : "N";

echo "<b>Before</b>:";
echo "<pre>";
print_r($selectBeforeUpdate);
echo "</pre>";
echo "<br/><b>Update</b>: $updated";
echo "<br/><b>After</b>:";
echo "<pre>";
print_r($selectAfterUpdate);
echo "</pre>";