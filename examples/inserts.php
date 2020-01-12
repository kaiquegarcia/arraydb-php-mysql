<?php
include_once "config.php";

\ArrayDB\Database\Settings::setConnectionConfig(connectorSettings);

$db = new \ArrayDB\Database\Connector(table);

$inserted = $db->setFields(insertFields)->insert();
$selectAfterInsert = [];
$primaryValue = "";

if($inserted) {
    $primaryValue = $db->getLastInsertedId();

    $selectConditions = [
        primaryKeyColumn => $primaryValue,
    ];
    $selectAfterInsert = $db->setConditions($selectConditions)->select();
}

$inserted = $inserted ? "Y" : "N";

echo "<b>Insert</b>: $inserted";
echo "<pre>";
print_r($selectAfterInsert);
echo "</pre>";