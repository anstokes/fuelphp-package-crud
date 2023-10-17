<?php

namespace Anstech\Crud\FieldTypes\Trait;

trait Required
{
    public static function addRequired($array)
    {
        // Add required to properties
        $array['null'] = false;

        // Check if validation array already exists
        if (isset($array['validation']) && is_array($array['validation'])) {
            if (! in_array('required', $array['validation'])) {
                array_unshift($array['validation'], 'required');
            }
        } else {
            $array['validation'] = ['required'];
        }

        return $array;
    }
}
