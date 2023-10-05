<?php

namespace Anstech\Crud\FieldTypes;

use Fuel\Core\Inflector;

class Double
{
    public static function forge($name)
    {
        return [
            'data_type'  => 'double',
            'label'      => ucfirst(Inflector::humanize($name)),
            'null'       => false,
            'validation' => [
                'numeric_min' => [
                    -0.0,
                ],
                'numeric_max' => [0.0],
            ],
            'form'       => [
                'type' => 'number',
                'step' => 0.0,
                'min'  => -0.0,
                'max'  => 0.0,
            ],
        ];
    }
}
