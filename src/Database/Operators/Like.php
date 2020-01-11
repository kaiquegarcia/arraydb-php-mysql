<?php

namespace ArrayDB\Database\Operators;

class Like extends AbstractOperator
{

    public function getQuerySymbol(): string
    {
        return " LIKE ";
    }
}