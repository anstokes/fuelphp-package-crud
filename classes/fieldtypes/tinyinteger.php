<?php

namespace Anstech\Crud\FieldTypes;

use Fuel\Core\Inflector;

class TinyInteger extends Integer
{
    protected static $max = 128;
}
