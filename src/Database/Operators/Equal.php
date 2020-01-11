<?php

namespace ArrayDB\Database\Operators;

class Equal extends AbstractOperator
{

    public function getQuerySymbol(): string
    {
        return "=";
    }
}