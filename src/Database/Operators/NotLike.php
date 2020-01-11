<?php

namespace ArrayDB\Database\Operators;

class NotLike extends Like
{

    public function getQuerySymbol(): string
    {
        return " NOT LIKE ";
    }
}