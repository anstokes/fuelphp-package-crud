<?php

namespace Anstech\Crud\FieldTypes\Required;

use Anstech\Crud\FieldTypes\Trait\Required;

class StringType extends \Anstech\Crud\FieldTypes\StringType
{
    use Required;

    public static function forge($name)
    {
        return static::addRequired(parent::forge($name));
    }
}
