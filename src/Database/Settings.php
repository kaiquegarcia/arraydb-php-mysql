<?php

namespace ArrayDB\Database;

use ArrayDB\Exceptions\UnexpectedValueException;
use ArrayDB\Exceptions\WrongTypeException;
use ArrayDB\Utils\ArrayHelper;

class Settings
{
    /**
     * @var array $CONNECTION_CONFIG: this will be the default config to the project
     *
     * notice:  it's not recommended to fill your connection config directly here
     *          you should use the static method Settings::setConnectionConfig instead
     */
    public static $CONNECTION_CONFIG = [
        "host" => "",
        "username" => "",
        "password" => "",
        "schema" => "",
        "charset" => "utf8",
    ];

    /**
     * @param array $settings
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    public static function setConnectionConfig(array $settings): void
    {
        self::$CONNECTION_CONFIG = Mysql::prepareConnectionInput($settings);
    }
}