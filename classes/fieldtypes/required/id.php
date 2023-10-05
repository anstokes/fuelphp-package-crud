<?php

namespace Anstech\Crud\FieldTypes\Required;

class Id
{
    public static function forge($name)
    {
        return [
            'data_type'  => 'int',
            'label'      => 'Id',
            'null'       => false,
            'validation' => [
                0             => 'required',
                'numeric_min' => [0],
                'numeric_max' => [2147483647],
            ],
            'form'       => ['type' => false],
        ];
    }
}
