<?php

namespace Anstech\Crud\FieldTypes;

use Fuel\Core\Inflector;

class Integer
{
    protected static $max = 2147483648;

    public static function forge($name)
    {
        return [
            'data_type'  => 'int',
            'label'      => ucfirst(Inflector::humanize($name)),
            'null'       => false,
            'validation' => [
                'numeric_min' => [
                    -static::$max,
                ],
                'numeric_max' => [(static::$max - 1)],
            ],
            'form'       => [
                'type' => 'number',
                'min'  => -static::$max,
                'max'  => (static::$max - 1),
            ],
        ];
    }
}
