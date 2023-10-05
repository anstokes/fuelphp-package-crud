<?php

namespace Anstech\Crud\Table;

use Fuel\Core\Input;

class JQuery
{
    public static function datatable($class, $url = false)
    {
        // Check if requesting data (via AJAX)
        if (Input::post('sEcho', false) || Input::post('action', false)) {
            // Create DataTables Editor instance and process the data
            $editor = \DataTables\Editor::forge($class)->addFields();

            // Add editor options
            static::datatableEditorOptions($editor);

            return [
                'editor',
                $editor,
            ];
        } else {
            // Create DataTables instance, configure and display table
            $generator = \DataTables\Generator::forge($class)
                ->addFields()
                ->enableServerSide(true)
                ->setAjaxURL($url)                                                  // Set URL to load data into table
                ->enableExtension('Editor', [])
                ->enableExtension('TableTools', [
                    'rowSelectMode' => 'single',                                    //  other modes (none / multi)
                    'defineButtons' => '"csv", "xls", "pdf", "print"',              // '"copy", "csv", "xls", "pdf", "print"',
                        // '{ "sExtends": "collection", "sButtonText": "Save", "aButtons": [ "csv", "xls", "pdf" ] }'
                        // http://datatables.net/extras/tabletools/buttons - in particular select & select_single types
                ])
                       ->enableExtension('ColumnFilter', [
                           'setColumnFilters' => trim(static::columnFilters($class), '[]'),
                       ])
                       ->enableLengthMenu(true, false, [10 => '10', 25 => '25', 50 => '50', 100 => '100', 250 => '250', 500 => '500'])
                       ->sDom('Tfrtip<\'left\'l>')
                   ;

                   // Add generator options
                   static::datatableGeneratorOptions($generator);

                   return [
                       'generator',
                       $generator,
                   ];
        }
    }


    // Stub for configuring editor on a model basis
    protected static function datatableEditorOptions($editor)
    {
    }


    //  Stub for configugring generator on a model basis
    protected static function datatableGeneratorOptions($generator)
    {
    }


    public static function columnFilters($class, $json = true)
    {
        //'{ type: "text" }, { type: "select", values: [{value: 1, label: "Yes"}, {value: 0, label: "No"}]}'
        $column_filters = [];
        $object = $class::forge();
        $field_names = $object->field_names();
        foreach ($class::properties() as $field => $properties) {
            if (in_array($field, $field_names)) {
                switch ($properties['form']['type']) {
                    case 'select':
                        $column_filters[] = [
                            'type'   => 'select',
                            'values' => static::columnFilterValues($properties['form']['options']),
                        ];
                        break;

                    default:
                        $column_filters[] = ['type' => 'text'];
                        break;
                }
            }
        }

        return ($json ? json_encode($column_filters) : $column_filters);
    }


    public static function columnFilterValues($options, $json = false)
    {
        $column_filter_values = [];
        foreach ($options as $value => $label) {
            $column_filter_values[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return ($json ? json_encode($column_filter_values) : $column_filter_values);
    }
}
