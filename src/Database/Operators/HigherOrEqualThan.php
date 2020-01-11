<?php

namespace ArrayDB\Database\Operators;

class HigherOrEqualThan extends AbstractOperator
{

    public function getQuerySymbol(): string
    {
        return ">=";
    }
}