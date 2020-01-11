<?php

namespace ArrayDB\Database\Operators;

use ArrayDB\Exceptions\UnexpectedValueException;

abstract class AbstractOperator
{
    abstract public function getQuerySymbol(): string;

    protected function prepareQueryValue($inputValue, bool $usePreparedStatement, string $stringWrapper): array
    {
        $args = [];
        if($usePreparedStatement) {
            $args = [$inputValue];
            $inputValue = "?";
        } elseif(is_string($inputValue)) {
            $inputValue = "{$stringWrapper}{$inputValue}{$stringWrapper}";
        }
        return [
            "query" => $inputValue,
            "args" => $args,
        ];
    }

    protected function exportQueryData(string $query, array $args = []): array
    {
        return [
            "query" => $query,
            "args" => $args,
        ];
    }

    public function getQueryData($inputValue, bool $usePreparedStatement, string $stringWrapper): array
    {
        $querySymbol = $this->getQuerySymbol();
        $info = $this->prepareQueryValue($inputValue, $usePreparedStatement, $stringWrapper);
        return $this->exportQueryData("{$querySymbol}{$info['query']}", $info['args']);
    }

    /**
     * @param $inputValue
     * @throws UnexpectedValueException
     */
    public function validateValue($inputValue): void
    {
        if(is_array($inputValue)) {
            throw new UnexpectedValueException("Operator expects non-array value");
        }
    }



    public function isRequested(string $requestSymbol, string &$inputName): bool
    {
        if(strpos($inputName, $requestSymbol) !== false) {
            $inputName = str_replace($requestSymbol, "", $inputName);
            return true;
        }
        return false;
    }
}