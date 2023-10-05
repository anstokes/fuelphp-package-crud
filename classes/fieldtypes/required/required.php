<?php

namespace Anstech\Crud\FieldTypes\Required;

trait Required
{
    public static function addRequired($array)
    {
        // Add required to properties
        $array['null'] = true;
        array_unshift($array['validation'], 'required');

        return $array;
    }
}
