<?php

namespace Anstech\Crud\Table;

use Anstech\Template\Enqueue;
use Fuel\Core\Arr;
use Fuel\Core\Config;
use Fuel\Core\DB;
use Fuel\Core\Inflector;
use Fuel\Core\Uri;
use App\User;
use Parser\View;

class Base
{
    /**
     * Default actions available on list / table
     * @var array
     * @access protected
     */
    protected static $crud_actions = [
        'edit'   => [
            // 'icon'  => 'fas fa-edit',
            'icon'  => 'las la-pen text-secondary font-16',
            'title' => 'Edit',
            'url'   => 'edit',
        ],
        'copy'   => [
            'icon'  => 'las la-copy text-secondary font-16',
            'title' => 'Copy',
            'url'   => 'copy',
        ],
        'delete' => [
            // 'icon'  => 'fas fa-trash',
            'icon'  => 'las la-trash-alt text-secondary font-16',
            'title' => 'Delete',
            'url'   => 'delete',
        ],
    ];

    /**
     * View to use when rendering actions
     * @var string
     * @access protected
     */
    protected static $crud_actions_view = 'table/actions/dropdown.mustache';

    /**
     * Hide primary keys from table
     * @var boolean
     * @access protected
     */
    protected static $hide_primary_keys = true;

    /**
     * The model which is used to populate this table
     * @var mixed
     * @access protected
     */
    protected $model = null;

    /**
     * The field(s) which define the default sort order
     * @var array
     * @access protected
     */
    protected $order_by = [];

    /**
     * The properties of the model
     * Will override the model if provided
     * @var array
     * @access protected
     */
    protected static $properties = [];

    /**
     * The fields to include in the table
     * @var array
     * @access protected
     */
    protected $table_fields = [];

    /**
     * The properties of the table
     * @var array
     * @access protected
     */
    protected $table_properties = [];

    /**
     * Visible columns
     * @var array
     * @access protected
     */
    protected $visible_columns = [];

    /**
     * The field(s) and value(s) used for search criteria
     * @var array
     * @access protected
     */
    protected $where = [];


    public function __construct($model)
    {
        $this->model = $model;

        if (! $this->table_properties) {
            $this->table_properties = $this->tableProperties();
        }
    }


    /**
     * Forge method to provide consistency with FuelPHP
     *
     * @return static
     */
    public static function forge($model)
    {
        return new static($model);
    }


    protected function addRelatedProperties(&$properties)
    {
        if ($relations = $this->model->relations()) {
            // Loop through relations
            foreach ($relations as $relation_name => $relation) {
                // Check whether to skip relation
                if (! $this->model->relationOptions($relation_name, 'crud_enabled', true)) {
                    continue;
                }

                // Check relation type/class
                switch (get_class($relation)) {
                    case 'Orm\BelongsTo':
                        // Field is defined on parent; only the value needs translating
                        // break;

                    case 'Orm\HasMany':
                        if ($related_properties = $this->model->relationProperties($relation_name, $relation)) {
                            $related_properties['table'] = ['view' => 'table/fields/relatedchips.mustache'];
                            $insert_properties = [$relation_name => $related_properties];

                            // Check whether to insert after a particular field
                            $key_from = $this->model->compatibleFieldName($relation->key_from[0], 'hydrate');
                            if ($add_after = $this->model->relationOptions($relation_name, 'add_after', $key_from)) {
                                Arr::insert_after_key($properties, $insert_properties, $add_after, true);
                            } else {
                                // Insert field at end
                                Arr::insert_assoc($properties, $insert_properties, count($properties));
                            }
                        }
                        break;

                    default:
                        // Relationship not relevant
                        break;
                }
            }
        }
    }


    protected function addRelatedObjects($object)
    {
        if ($relations = $this->model->relations()) {
            // Loop through relations
            foreach ($relations as $relation_name => $relation) {
                // Check whether to skip relation
                if (! $this->model->relationOptions($relation_name, 'crud_enabled', true)) {
                    continue;
                }

                // Check relation type/class
                switch (get_class($relation)) {
                    case 'Orm\BelongsTo':
                    case 'Orm\HasMany':
                        // TODO Only include required fields to 'label' related model
                        $object->related($relation_name);
                        break;

                    default:
                        // Relationship not relevant
                        break;
                }
            }
        }
    }


    protected function columnVisible($column)
    {
        // Check for table hidden, type and visible properties
        $column_table_hidden = Arr::get($this->table_properties, $column . '.table.hidden', null);
        $column_table_type = Arr::get($this->table_properties, $column . '.table.type', null);
        $column_table_visible = Arr::get($this->table_properties, $column . '.table.visible', null);

        // If field is explicitly hidden/shown on table then use this setting
        if ($column_table_hidden || ($column_table_type === false) || ($column_table_visible === false)) {
            return false;
        } elseif (($column_table_hidden === false) || $column_table_type || $column_table_visible) {
            return true;
        }

        // Check for form hidden, type and visible properties
        $column_form_hidden = Arr::get($this->table_properties, $column . '.form.hidden', null);
        $column_form_type = Arr::get($this->table_properties, $column . '.form.type', null);
        $column_form_visible = Arr::get($this->table_properties, $column . '.form.visible', null);

        // If field is explicitly hidden on form then use this setting
        if ($column_form_hidden || ($column_form_type === false) || ($column_form_visible === false)) {
            return false;
        }

        // Check if column is in primary keys
        if (static::$hide_primary_keys && ($primary_keys = $this->model::primary_key())) {
            if (in_array($column, $primary_keys)) {
                return false;
            }
        }

        // Visible by default
        return true;
    }


    /**
     * Return array of CRUD actions
     *
     * @param object $object Model object
     *
     * @return array
     */
    protected function crudActions($object = false)
    {
        if (! $object) {
            return static::$crud_actions;
        }

        $actions = [];

        foreach (static::$crud_actions as $action) {
            // echo '<pre>'; var_dump($object); exit;
            $actions[] = [
                'icon'  => $action['icon'],
                'label' => $object->getLabel(),
                'title' => $action['title'],
                'url'   => Uri::current() . '/' . ($action['url'] ? $action['url'] . '/' : '') . $object->{$this->model::singularPrimaryKey()},
            ];
        }

        return $actions;
    }


    /**
     * Render CRUD actions as HTML string
     *
     * @param object $object Model object
     *
     * @return string
     */

    protected function crudActionsHtml($object = false)
    {
        if ($actions = $this->crudActions($object)) {
            return View::forge(static::$crud_actions_view, ['actions' => $actions], false);
        }

        return '';
    }


    /**
     * Returns the relation, or false if not related
     *
     * @param string $field
     * @param string $relation_type
     *
     * @return mixed
     */
    protected function getFieldRelation($field, $relation_type = false)
    {
        foreach ($this->model->relations() as $relation) {
            if (! $relation_type || (get_class($relation) === $relation_type)) {
                if (in_array($field, $relation->key_from) || in_array($field, $relation->key_to)) {
                    return $relation;
                }
            }
        }

        return false;
    }


    /**
     * Returns value of a given field
     *
     * @param string    $field
     * @param array     $field_properties
     * @param object    $object
     *
     * @return mixed
     */
    protected function getFieldValue($field, $field_properties, $object)
    {
        // Check if field is a relation name
        if (($relation = $this->model->relations($field)) && ($object->{$field})) {
            // Use label from related model
            if (is_array($object->{$field})) {
                $value = [];
                foreach ($object->{$field} as $related_object) {
                    $value[] = [
                        'iri'   => $related_object->getIri(),
                        'label' => $related_object->getLabel(),
                    ];
                }
            } else {
                $value = [
                    'iri'   => $object->{$field}->getIri(),
                    'label' => $object->{$field}->getLabel(),
                ];
            }
        } elseif ($relation = $this->getFieldRelation($field, 'Orm\BelongsTo')) {
            // Use label from related object
            if (isset($object->{$relation->name}) && ($related_object = $object->{$relation->name})) {
                $value = $related_object->getLabel();
            } else {
                // Relation does not exist
            }
        } else {
            // Default value
            $value = (isset($object->{$field}) ? $object->{$field} : null);
        }

        // Check for field value callback
        if ($field_table_value_callback = Arr::get($this->table_properties, $field . '.table.value.callback')) {
            if (is_callable($field_table_value_callback)) {
                $value = call_user_func($field_table_value_callback, $value);
            }
        }

        return $value;
    }


    /**
     * Returns the model objects
     *
     * @param bool $active_only
     *
     * @return array
     */
    protected function getObjects()
    {
        $objects = $this->model::query();

        // Add related field
        $this->addRelatedObjects($objects);

        // Where
        $this->where($objects);

        // Order by
        $this->orderBy($objects);

        // TODO - only outside try for debugging
        $result = $objects->get();
        // var_dump($result); exit;
        // var_dump(\DB::last_query()); exit;
        return $result;

        try {
            return $objects->get();
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function orderBy($objects)
    {
        $table_properties = $this->tableProperties();

        // Check if order by is set
        if (! $this->order_by) {
            // Attempt to calculate default order by from model title
            $title_column = strtolower($this->model->getTitle());
            if (Arr::key_exists($table_properties, $title_column)) {
                $this->order_by = [$title_column => 'asc'];
            } elseif (Arr::key_exists($table_properties, 'description')) {
                // Fallback to description, where available
                $this->order_by = ['description' => 'asc'];
            }
        }

        if ($order_by = $this->order_by) {
            foreach ($order_by as $field => $direction) {
                if (Arr::key_exists($table_properties, $field)) {
                    $objects->order_by($order_by);
                }
            }
        }
    }


    public function render()
    {
        // Queue the relevant plugin for selects
        Enqueue::enqueuePlugins('simple-datatables');

        // Show the data
        return View::forge('table/simple.mustache', [
            'addUrl'   => Uri::current() . '/new',
            'headings' => $this->tableHeadings(),
            'data'     => $this->tableData(),
            'title'    => $this->model->getTitle(),
            'titles'   => $this->model->getTitle(true),
        ], false);
    }


    protected function renderField($field, $field_properties, $object)
    {
        // Get field value from object
        $value = $this->getFieldValue($field, $field_properties, $object);

        // Check for field renderer (view)
        if ($field_table_view = Arr::get($this->table_properties, $field . '.table.view')) {
            return View::forge($field_table_view, [
                'field'      => $field,
                'object'     => $object,
                'properties' => $field_properties,
                'value'      => $value,
            ]);
        }

        switch ($field) {
            case '@actions':
                return $this->crudActionsHtml($object);

            default:
                // Convert array to string
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                // Convert option value to label
                if ($field_form_options = Arr::get($this->table_properties, $field . '.form.options')) {
                    $value = Arr::get($field_form_options, $value, $value);
                }
                break;
        }

        return $value;
    }


    protected function tableData()
    {
        $fields = $this->visibleColumns();
        $rows = [];

        foreach ($this->getObjects() as $object) {
            $data = [];

            foreach ($fields as $field => $field_properties) {
                $data[] = $this->renderField($field, $field_properties, $object);
            }

            // Add actions
            if ($this->crudActions()) {
                $data[] = $this->renderField('@actions', [], $object);
            }

            array_push($rows, array_values($data));
        }

        // echo '<pre>'; var_dump($rows); exit;
        return $rows;
    }


    /**
     * Return label for a given column
     *
     * @param mixed $column
     *
     * @return string
     */
    protected function tableHeading($column)
    {
        $label = Arr::get($this->table_properties, $column . '.label', ucfirst($column));

        return ['label' => $label];
    }


    /**
     * Return array of table headings (column names)
     *
     * @return array
     */
    protected function tableHeadings()
    {
        $columns = array_map([$this, 'tableHeading'], array_keys($this->visibleColumns()));

        // Add actions
        if ($this->crudActions()) {
            // Label for 'actions' column
            $columns[] = '';
        }

        return array_values($columns);
    }


    protected function tableProperties()
    {
        $properties = static::$properties ?: $this->model::properties();

        // Add related properties
        $this->addRelatedProperties($properties);

        //echo '<pre>'; var_dump($properties); exit;
        if ($fields = $this->table_fields) {
            $properties = array_filter($properties, function ($key) use ($fields) {
                return in_array($key, $fields);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $properties;
    }


    protected function visibleColumns()
    {
        // Use cached result, if already available
        if (! $this->visible_columns) {
            // Find columns from properties
            $this->visible_columns = $this->table_properties;

            // Check if each column is visible
            foreach ($this->visible_columns as $field => $field_properties) {
                if (! $this->columnVisible($field)) {
                    unset($this->visible_columns[$field]);
                }
            }
        }

        return $this->visible_columns;
    }


    protected function where($objects)
    {
        $fieldNames = array_keys($this->tableProperties());

        /*
        foreach (['active', 'enabled'] as $active_field)
        {
            if (in_array($active_field, $field_names))
            {
                $objects->where($active_field, true);
            }
        }
        */

        // Only show items valid at the current time
        // Handled by ORM query builder
        /*
        if (property_exists($this->model, '_temporal')) {
            $now = time();
            $temporalProperties = $this->model->temporal_properties();
            $objects->where($temporalProperties['start_column'], '<=', $now);
            $objects->where($temporalProperties['end_column'], '>=', $now);
        }
        */
    }
}
