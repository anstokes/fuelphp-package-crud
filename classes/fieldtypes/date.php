<?php

namespace Anstech\Crud\FieldTypes;

use App\Time;
use App\User;
use Fuel\Core\Inflector;

class Date
{
    public static function forge($name)
    {
        return [
            'data_type'  => 'int',
            'label'      => ucfirst(Inflector::humanize($name)),
            'null'       => false,
            'validation' => [
                'numeric_min' => [
                    -2147483648,
                ],
                'numeric_max' => [2147483647],
            ],
            'form'       => [
                'type' => 'number',
                'min'  => -2147483648,
                'max'  => 2147483647,
            ],
            'table'      => [
                'value' => [
                    'callback' => [
                        get_called_class(),
                        'tableValue',
                    ],
                ],
            ],
        ];
    }

    public static function tableValue($value)
    {
        return Time::browserDate(User::shortDateFormat(), $value);
    }
}
