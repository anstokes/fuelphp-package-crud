<?php

namespace Anstech\Crud\FieldTypes\Required;

use Anstech\Crud\FieldTypes\Trait\Required;

class Varchar extends \Anstech\Crud\FieldTypes\Varchar
{
    use Required;

    public static function forge($name, $length = 255)
    {
        return static::addRequired(parent::forge($name, $length));
    }
}
