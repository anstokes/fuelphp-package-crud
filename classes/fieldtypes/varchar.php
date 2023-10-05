<?php

namespace Anstech\Crud\FieldTypes;

use Fuel\Core\Inflector;

class Varchar
{
    protected static $length = 255;

    public static function forge($name)
    {
        return [
            'data_type'  => 'varchar',
            'label'      => ucwords(Inflector::humanize($name)),
            'null'       => false,
            'validation' => [
                'max_length' => [static::$length],
            ],
            'form'       => [
                'type'      => 'text',
                'maxlength' => static::$length,
            ],
        ];
    }
}
