<?php

namespace Anstech\Crud\FieldTypes;

use Fuel\Core\Inflector;

class Temporal
{
    public static function forge($name)
    {
        return [
            'data_type' => 'int',
            'label'     => ucwords(Inflector::humanize($name)),
        ];
    }
}
