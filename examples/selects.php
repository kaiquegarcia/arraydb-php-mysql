<?php

include_once "config.php";

\ArrayDB\Database\Settings::setConnectionConfig(connectorSettings);

$db = new \ArrayDB\Database\Connector(table);

$db->setAlias(alias);

$result1 = $db->setConditions(simpleSelectConditions)->select();

$result2 = $db
    ->join(joinSettings) // you can call this anytimes you want to append join settings
    ->setConditions(joinSelectConditions)
    ->select(joinSelectSelectors); // after this, joinSettings will be reseted on Connector

$result3 = $db->setConditions(complexSelectConditions)->select();

echo "<pre>";
var_dump($result1);
echo "<hr/>";
var_dump($result2);
echo "<hr/>";
var_dump($result3);
echo "</pre>";