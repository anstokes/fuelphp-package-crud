<?php

namespace Anstech\Crud\FieldTypes;

class Company
{
    public static function forge($name)
    {
        return [
            'data_type'  => 'int',
            'label'      => 'Company',
            'null'       => false,
            'validation' => [
                'numeric_min' => [0],
                'numeric_max' => [2147483647],
            ],
            'form'       => ['type' => false],
        ];
    }
}
