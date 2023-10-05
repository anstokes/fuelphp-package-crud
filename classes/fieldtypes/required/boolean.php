<?php

namespace Anstech\Crud\FieldTypes\Required;

use Fuel\Core\Inflector;

class Boolean
{
    public static function forge($name)
    {
        return [
            'data_type'  => 'bool',
            'label'      => ucfirst(Inflector::humanize($name)),
            'null'       => false,
            'default'    => '1',
            'validation' => ['required'],
            'form'       => [
                'type'    => 'radio',
                'value'   => '1',
                'options' => [
                    1 => 'Yes',
                    0 => 'No',
                ],
            ],
            'table'      => ['view' => 'table/fields/active.mustache'],
        ];
    }
}
