<?php

namespace Anstech\Crud\FieldTypes\Required;

use Fuel\Core\Inflector;

class Uuid
{
    public static function forge($name)
    {
        return [
            'data_type'  => 'varchar',
            'label'      => ucfirst(Inflector::humanize($name)),
            'null'       => true,
            'validation' => [
                0            => 'required',
                'max_length' => [36],
            ],
            'form'       => [
                'type'      => 'text',
                'maxlength' => 36,
            ],
        ];
    }
}
