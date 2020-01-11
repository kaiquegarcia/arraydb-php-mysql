<?php

namespace ArrayDB\Utils;

class StringHelper
{
    public static function multiReplace(string $origin, array $replaces)
    {
        if (!empty($replaces)) {
            foreach ($replaces as $from => $to) {
                $origin = str_replace("{" . $from . "}", $to, $origin);
            }
        }
        return $origin;
    }
}