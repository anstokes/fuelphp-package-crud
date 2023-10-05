<?php

namespace Anstech\Crud\FieldTypes;

class CreatedAt
{
    public static function forge($name)
    {
        return [
            'data_type'  => 'int',
            'label'      => 'Created at',
            'null'       => false,
            'validation' => [
                'numeric_min' => [
                    -2147483648,
                ],
                'numeric_max' => [2147483647],
            ],
            'form'       => ['type' => false],
        ];
    }
}
