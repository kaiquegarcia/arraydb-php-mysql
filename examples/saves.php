<?php
include_once "config.php";

\ArrayDB\Database\Settings::setConnectionConfig(connectorSettings);

$db = new \ArrayDB\Database\Connector(table);

$inserted = $db->setFields(insertFields)->save();
$selectAfterInsert = $selectAfterUpdate = [];
$primaryValue = "";
$updated = false;

if($inserted) {
    $primaryValue = $db->getLastInsertedId();

    $selectConditions = [
        primaryKeyColumn => $primaryValue,
    ];
    $selectAfterInsert = $db->setConditions($selectConditions)->select();

    $updateFields = updateFields;
    $updateFields[primaryKeyColumn] = $primaryValue;
    $updated = $db->setFields($updateFields)->save();

    if($updated) {
        $selectAfterUpdate = $db->setConditions($selectConditions)->select();
    }
}

$inserted = $inserted ? "Y" : "N";
$updated = $updated ? "Y" : "N";

echo "<b>Insert</b>: $inserted";
echo "<pre>";
print_r($selectAfterInsert);
echo "</pre>";
echo "<br/><b>LastInsertedId</b>: $primaryValue";
echo "<br/><b>Update</b>: $updated";
echo "<pre>";
print_r($selectAfterUpdate);
echo "</pre>";