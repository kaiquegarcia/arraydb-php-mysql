<?php

namespace ArrayDB\Utils;

class ArrayHelper
{
    public static function getUnset(array &$arr, $index)
    {
        if (!isset($arr[$index])) {
            return null;
        }
        $value = $arr[$index];
        unset($arr[$index]);
        return $value;
    }

    public static function isStringIndexed(array $arr): bool
    {
        return count(array_filter(array_keys($arr), "is_string")) === count($arr);
    }

    public static function hasStringIndex(array $arr): bool
    {
        return count(array_filter(array_keys($arr), "is_string")) > 0;
    }
}