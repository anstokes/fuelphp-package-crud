<?php

namespace Anstech\Crud\FieldTypes\Required;

class Integer extends \Anstech\Crud\FieldTypes\Integer
{
    use Required;

    public static function forge($name)
    {
        return static::addRequired(parent::forge($name));
    }
}
