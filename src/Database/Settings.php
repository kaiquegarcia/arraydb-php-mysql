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
    private static function validateConnectionInputs(array $settings): void
    {
        if(!isset($settings['host']) || !is_string($settings['host'])) {
            throw new UnexpectedValueException("You must inform a host to connect.");
        } elseif(!isset($settings['username']) || !is_string($settings['username'])) {
            throw new UnexpectedValueException("You must inform an username.");
        } elseif(!isset($settings['password']) || !is_string($settings['password'])) {
            throw new UnexpectedValueException("You must inform a password.");
        } elseif(isset($settings['schema']) && !is_string($settings['schema'])) {
            throw new WrongTypeException("schema should be string.");
        } elseif(isset($settings['charset']) && !is_string($settings['charset'])) {
            throw new WrongTypeException("charset should be string.");
        }
    }

    /**
     * @param array $settings
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    public static function setConnectionConfig(array $settings): void
    {
        self::validateConnectionInputs($settings);
        $charset = ArrayHelper::getUnset($settings, "charset");
        if(!$charset) {
            $charset = "utf8";
        }
        self::$CONNECTION_CONFIG = [
            "host" => ArrayHelper::getUnset($settings, "host"),
            "username" => ArrayHelper::getUnset($settings, "username"),
            "password" => ArrayHelper::getUnset($settings, "password"),
            "schema" => ArrayHelper::getUnset($settings, "schema"),
            "charset" => $charset
        ];
    }
}