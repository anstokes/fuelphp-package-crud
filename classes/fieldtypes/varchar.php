<?php

namespace Anstech\Crud\FieldTypes;

use Fuel\Core\Inflector;

class Varchar
{
    public static function forge($name, $length = 255)
    {
        return [
            'data_type'  => 'varchar',
            'label'      => ucwords(Inflector::humanize($name)),
            'null'       => false,
            'validation' => [
                'max_length' => [$length],
            ],
            'form'       => [
                'type'      => 'text',
                'maxlength' => $length,
            ],
        ];
    }
}
