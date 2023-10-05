<?php

namespace Anstech\Crud\FieldTypes;

use Fuel\Core\Inflector;

class StringType
{
    protected static $length = 65535;

    public static function forge($name)
    {
        return [
            'data_type'  => 'string',
            'label'      => ucwords(Inflector::humanize($name)),
            'null'       => false,
            'validation' => [
                'max_length' => [65535],
            ],
            'form'       => ['type' => 'textarea'],
        ];
    }
}
