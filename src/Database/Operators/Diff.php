<?php

namespace ArrayDB\Database\Operators;

class Diff extends AbstractOperator
{

    public function getQuerySymbol(): string
    {
        return "!=";
    }
}