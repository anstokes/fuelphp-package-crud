<?php

use Fuel\Core\Autoloader;

Autoloader::add_namespace('Anstech\Crud', __DIR__ . '/classes/');
Autoloader::add_namespace('Anstech\Crud\FieldTypes', __DIR__ . '/classes/fieldtypes');
Autoloader::add_classes([
    'Anstech\Crud\Form\Basic'   => __DIR__ . '/classes/form/basic.php',
    'Anstech\Crud\Table\JQuery' => __DIR__ . '/classes/table/jquery.php',
    'Anstech\Crud\Table\Simple' => __DIR__ . '/classes/table/simple.php',
]);
