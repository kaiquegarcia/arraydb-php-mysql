<?php

namespace ArrayDB\Database;

use ArrayDB\Database\Operators\AbstractOperator;
use ArrayDB\Exceptions\UnexpectedValueException;

class OperatorDecoder
{
    const FORCED_AND_OPERATOR = "&";
    const OPERATORS_PRECEDENCE = [
        ">=" => "HigherOrEqualThan",
        "<=" => "LowerOrEqualThan",
        "!:" => "NotIn",
        "!~" => "NotLike",
        ">" => "HigherThan",
        "<" => "LowerThan",
        ":" => "In",
        "~" => "Like",
        "!" => "Diff",
        "=" => "Equal",
    ];

    /**
     * @param string $inputName
     * @param $inputValue
     * @return AbstractOperator|null
     * @throws UnexpectedValueException
     */
    public static function get(string &$inputName, $inputValue): ?AbstractOperator
    {
        $classPath = "ArrayDB\\Database\\Operators\\";
        foreach(self::OPERATORS_PRECEDENCE as $symbol => $className) {
            $class = "{$classPath}{$className}";
            $operator = new $class();
            /** @var AbstractOperator $operator */
            if($operator->isRequested($symbol, $inputName) || $symbol === "=") {
                $operator->validateValue($inputValue);
                return $operator;
            }
        }
        return null;
    }
}