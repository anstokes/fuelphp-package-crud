<?php

namespace Anstech\Crud\FieldTypes\Required;

class Varchar extends \Anstech\Crud\FieldTypes\Varchar
{
    use Required;

    public static function forge($name)
    {
        return static::addRequired(parent::forge($name));
    }
}
