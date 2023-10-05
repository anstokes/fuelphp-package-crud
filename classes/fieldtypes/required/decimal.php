<?php

namespace Anstech\Crud\FieldTypes\Required;

class Decimal extends \Anstech\Crud\FieldTypes\Decimal
{
    use Required;

    public static function forge($name)
    {
        return static::addRequired(parent::forge($name));
    }
}
