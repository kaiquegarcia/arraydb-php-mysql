<?php

namespace ArrayDB\Database\Operators;

class LowerThan extends AbstractOperator
{
    public function getQuerySymbol(): string
    {
        return "<";
    }
}