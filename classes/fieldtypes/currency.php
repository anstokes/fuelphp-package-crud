<?php

namespace Anstech\Crud\FieldTypes;

use Fuel\Core\Inflector;

class Currency
{
    public static function forge($name)
    {
        return [
            'data_type'  => 'decimal',
            'label'      => ucfirst(Inflector::humanize($name)),
            'null'       => false,
            'validation' => [
                'numeric_min' => [
                    -99999999.998999998,
                ],
                'numeric_max' => [99999999.998999998],
            ],
            'form'       => [
                'type' => 'number',
                'step' => 0.999,
                'min'  => -99999999.998999998,
                'max'  => 99999999.998999998,
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
        return \Model\Finance\Currency::currencyFormat($value);
    }
}
