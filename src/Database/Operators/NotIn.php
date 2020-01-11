<?php

namespace ArrayDB\Database\Operators;

class NotIn extends In
{
    public function getQuerySymbol(): string
    {
        return " NOT IN ";
    }
}