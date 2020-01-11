<?php

namespace ArrayDB\Database\Operators;

class HigherThan extends AbstractOperator
{

    public function getQuerySymbol(): string
    {
        return ">";
    }
}