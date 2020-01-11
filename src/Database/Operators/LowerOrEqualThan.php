<?php

namespace ArrayDB\Database\Operators;

class LowerOrEqualThan extends AbstractOperator
{
    public function getQuerySymbol(): string
    {
        return "<=";
    }
}