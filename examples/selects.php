<?php

include_once "config.php";

\ArrayDB\Database\Settings::setConnectionConfig(connectorSettings);

$db = new \ArrayDB\Database\Connector(table);

$db->setAlias(alias);

$result1 = $db->setFields(simpleSelectFields)->select();

$db->setAlias(alias);

$result2 = $db
    ->join(joinSettings) // you can call this anytimes you want to append join settings
    ->setFields(joinSelectFields)
    ->select(joinSelectSelectors); // after this, joinSettings will be reseted on Connector

$result3 = $db->setFields(complexSelectFields)->select();

echo "<pre>";
var_dump($result1);
echo "<hr/>";
var_dump($result2);
echo "<hr/>";
var_dump($result3);
echo "</pre>";