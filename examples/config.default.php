<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../src/spl_autoload.php";

const connectorSettings = [
    "host" => "localhost",
    "username" => "root",
    "password" => "",
    "schema" => "",
    "charset" => null, // use defaults (utf8)
];

const table = "some_table";

// selects.php
const simpleSelectConditions = [
    "~email" => "kaiquegarcia%",
];


const alias = "myAlias"; // this will appear a lot above :D
// result: SELECT * FROM some_table WHERE email LIKE 'kaiquegarcia%'

// if someday you forgot the joinSettings params, you can use the Connector::generateJoinSettings helper
// but it's just a helper to generate an array like this:
const joinSettings = [
    "table" => "another_table",
    "alias" => "anotherAlias",
    "direction" => \ArrayDB\Database\Connector::INNER_JOIN,
    "conditions" => [ // the conditions syntax are equal to the select syntax
        "`anotherAlias`.`another_table_id`" => "`myAlias`.`foreign_key_to_another_table_id`",
        ":`anotherAlias`.something" => ["some", "value", "(s)"],
    ],
];

// in joined selections, you should inform any alias on array indexes

const joinSelectSelectors = [
    "`myAlias`.`email`",
    "`anotherAlias`.`another_table_id`",
];
const joinSelectConditions = [
    ":`myAlias`.ID" => [1, 2, 3, 4, 5],
    "!:`anotherAlias`.ID" => 1000,
];

// if you don't have to join, then you don't need to inform anything
const complexSelectConditions = [
    ":ID" => [1, 2, 3, 4, 5], // ID IN (1,2,3,4,5)
    "__" => [ // AND (
        "!phone" => "some_phone", // phone != 'some_phone'
        "&___" => [ // OR ( ++++ here we change child append scope to AND
            "ID" => 1, // ID=1
            "~email" => "kaiquegarcia%", // AND email LIKE 'kaiquegarcia%'
        ], // )
    ],
];

// result: SELECT * FROM some_table WHERE ID IN (1,2,3,4,5) AND (phone !='some_phone' OR (ID=1 AND email LIKE 'kaiquegarcia%'))

// saves.php: we will use the table from const table
const insertFields = [
    "someColumn" => "someValue",
    "someColumn2" => "anotherValue",
    // ....
];

// as you're inserting, you don't know which id to use, right?
// we'll call Connector->getLastInsertedId to get that value
// but as I don't know how you labelled the primary key column
// I need you to write the column name here:
const primaryKeyColumn = "ID";

// I'll override the primaryKeyValue here,
// it's here just to your knowledge ;)
const updateFields = [
    primaryKeyColumn => "primaryValue",
    "someColumn" => "someNewValue",
    // ....
];

// delete.php: same table from const table
const deleteConditions = [
    primaryKeyColumn => "primaryValue", // put here an id to delete
];