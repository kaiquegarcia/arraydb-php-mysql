<?php

namespace ArrayDB\Database\Operators;

use ArrayDB\Exceptions\UnexpectedValueException;
use ArrayDB\Utils\ArrayHelper;

class In extends AbstractOperator
{

    public function getQuerySymbol(): string
    {
        return " IN ";
    }

    public function getQueryData($inputValue, bool $usePreparedStatement, string $stringWrapper): array
    {
        $query = $this->getQuerySymbol() . "(";
        $append = "";
        $args = [];
        foreach($inputValue as $value) {
            $info = $this->prepareQueryValue($value, $usePreparedStatement, $stringWrapper);
            $query .= "{$append}{$info['query']}";
            $args = array_merge($args, $info['args']);
            $append = ", ";
        }
        $query .= ")";
        return $this->exportQueryData($query, $args);
    }

    /**
     * @param $inputValue
     * @throws UnexpectedValueException
     */
    public function validateValue($inputValue): void
    {
        if(!is_array($inputValue)) {
            throw new UnexpectedValueException("Operator expects array");
        } elseif(empty($inputValue)) {
            throw new UnexpectedValueException("Operator expects populated array");
        } elseif(ArrayHelper::hasStringIndex($inputValue)) {
            throw new UnexpectedValueException("Operator expects numeric indexed array");
        }
    }
}