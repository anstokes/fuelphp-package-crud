<?php

namespace Anstech\Crud;

use Anstech\Rest\Json\Schema;
use Fuel\Core\DB;
use Fuel\Core\Inflector;
use Fuel\Core\Input;
use Fuel\Core\Module;
use Fuel\Core\Validation;

/**
 * Model to support REST API controller
 */
class Rest
{
    protected $model = null;

    public static function forge($resource, $apiController)
    {
        /*
        $namespaced_resource = false;

        // Convert to correct namespace and class
        $pos = strpos($resource, '_');
        if ($pos !== false)
        {
            $namespaced_resource = substr_replace($resource, '\\', $pos, 1);
        }

        if ($namespaced_resource)
        {
            $module = trim(Inflector::get_namespace($namespaced_resource), '\\');
            $class = $resource;
        }
        else
        {
            $module = $resource;
            $class = $resource;
        }

        // Load module
        Module::load($module);
        */

        // Check model class exists
        if (class_exists($resource)) {
            $crud = new static();
            $crud->model = $resource::forge();
            return $crud;
        }

        return false;
    }

    public function getOne($id, $inputData = [])
    {
        // Check if schema request
        if ($id == 'schema') {
            return $this->getSchema();
        }

        $object = $this->model->find($id);

        if ($object) {
            return $this->cleanObject($object, $inputData);
        }

        return null;
    }


    public function getMany($inputData = [])
    {
        $query = $this->model
            ->query();

        // Apply filter, sorting etc.
        $this->query($query, $inputData);

        // Total count
        $total_count = $query->count();

        // Pagination
        $this->pagination($query);

        // Clean objects
        if ($objects = $query->get()) {
            foreach ($objects as $index => $object) {
                $objects[$index] = $this->cleanObject($object, $inputData);
            }
        }

        return [
            array_values($objects),
            $total_count,
        ];
    }


    public function getSchema()
    {
        $schema = [];

        $primary_keys = $this->model->primary_key();
        $fields = array_keys($this->cleanObject($this->model));

        foreach ($this->model->properties() as $field => $properties) {
            if (in_array($field, $fields) && ! in_array($field, $primary_keys)) {
                $schema[$field] = Schema::convertProperties($properties);
            }
        }

        return $schema;
    }


    public function createOne($data)
    {
        $object = $this->model::forge();
        return $this->update($object, $data);
    }


    public function updateOne($id, $data)
    {
        $object = $this->model->find($id);

        if ($object) {
            return $this->update($object, $data);
        }

        return [
            false,
            'Object not found',
        ];
    }


    protected function update($object, $data = [])
    {
        // Fake error
        /*
        return [
            false,
            'Fake error message'
        ];
        */

        // Validate the JSON
        $validate_object = Validation::forge(get_class($object))->add_model($object);
        if ($validate_object && ! $validate_object->run($data)) {
            // Add validation error to interface, if appropriate
            $errors = $validate_object->error();
            return [
                false,
                array_shift($errors)->get_message(),
            ];
        }

        // Update fields
        $primary_keys = $object->primary_key();
        foreach (array_keys($object->properties()) as $field) {
            if (! in_array($field, $primary_keys) && isset($data[$field])) {
                $object->{$field} = $data[$field];
            }
        }

        try {
            // Save object
            return [
                $object->save(),
                $this->cleanObject($object),
            ];
        } catch (\Exception $e) {
            // Error saving
            return [
                false,
                $e->getMessage(),
            ];
        }
    }


    public function deleteOne($id)
    {
        $object = $this->model->find($id);

        if ($object) {
            //try
            {
                return [
                    $object->delete(),
                    $this->cleanObject($object),
                ];
                }
                /*
                catch (\Exception $e)
                {
                // Error deleting
                return [
                    false,
                    $e->getMessage(),
                ];
                }
                *
                */
        }

        return [
            false,
            'Object not found',
        ];
    }


    /**
     * Cleans model object; converts object to array and removes 'private' fields
     *
     * @param  array $object
     *
     * @return array
     */
    protected function cleanObject($object, $inputData = [], $removeFields = ['companyId'])
    {
        // Fields to remove / rename
        $fields = [
            'include' => [],
            'remove'  => $removeFields,
            'rename'  => [],
        ];

        // Check if including related models
        $this->includeRelated($object, $inputData, $fields);

        if (! $object) {
            return $object;
        }

        return $this->toArray($object, $inputData, $fields);
    }


    protected function toArray($object, $inputData, $fields)
    {
        // Convert object to array
        $array = $object->to_array();

        // Remove fields
        $this->removeFields($array, $fields['remove']);

        // Filter to only the required fields, if supplied
        $this->filterFields($object, $array, $inputData);

        // Apply modifications
        $this->applyModifications($array, $inputData);

        // Rename fields
        $this->renameFields($array, $fields['rename']);

        // Include fields
        $this->includeFields($array, $fields['include']);

        return $array;
    }


    protected function query(&$query, $inputData = [])
    {
        $fields = $this->model->properties();
        $inputs = Input::get();

        if (isset($inputData['where']) && ($where = $inputData['where'])) {
            foreach ($where as $field => $whereProperties) {
                $comparator = (isset($whereProperties['comparator']) ? $whereProperties['comparator'] : DB::expr('NULL'));
                $value = (isset($whereProperties['value']) ? $whereProperties['value'] : DB::expr('NULL'));
                $query->where($field, $comparator, $value);
            }
        }

        foreach ($inputs as $input => $value) {
            switch ($input) {
                case '_sort':
                    // Sorting
                    if (isset($fields[$value])) {
                        $query->order_by([$value => Input::get('_order', 'ASC')]);
                    }
                    break;

                case '_order':
                    // Used by sorting
                    break;

                case '_start':
                case '_end':
                    // Pagination
                    break;

                case 'q':
                    // Global filtering
                    $query->where_open();
                    foreach (array_keys($fields) as $field) {
                         $query->or_where($field, 'LIKE', '%' . $value . '%');
                    }

                    $query->where_close();
                    break;

                default:
                    // Filtering
                    if (isset($fields[$input]) && ($fieldProperties = $fields[$input])) {
                        if (is_array($value)) {
                            $query->where($input, 'IN', $value);
                        } else {
                            // Type cast the value
                            $query->where($input, $this->typeCast($value, $fieldProperties));
                        }
                    }
                    break;
            }
        }
    }


    protected function pagination(&$query)
    {
        $start = Input::get('_start');
        $end = Input::get('_end');
        if (($start !== null) && ($end !== null)) {
            $count = ($end - $start);
            $query->limit($count);
            $query->offset($start);
        }
    }


    public function fieldValues($field)
    {
        $properties = $this->model->properties();

        // Check field exists
        if (isset($properties[$field])) {
            // Find distinct values
            $sql = 'SELECT DISTINCT ' . $field . ' FROM ' . $this->model->table();

            // Apply temporal filtering where relevant
            if (method_exists($this->model, 'temporal_properties') && ($temporalProperties = $this->model->temporal_properties())) {
                $now = time();
                $sql .= ' WHERE ' . $temporalProperties['start_column'] . ' <= ' . $now;
                $sql .= ' AND ' . $temporalProperties['end_column'] . ' > ' . $now;
            }

            // Order by
            $sql .= ' ORDER BY ' . $field;

            $values = DB::query($sql)->execute()->as_array();

            // Apply id and return
            $rows = array_map(
                function ($row) use ($field) {
                    return [
                        'id'   => $row[$field] ? $row[$field] : 'false',
                        $field => $row[$field],
                    ];
                },
                $values
            );
            return [
                array_values($rows),
                count($values),
            ];
        }

        return [
            [],
            0,
        ];
    }


    protected function typeCast($value, $properties)
    {
        if (isset($properties['data_type'])) {
            switch ($properties['data_type']) {
                case 'bool':
                    if (($value) === 'true') {
                        $value = true;
                    } elseif (($value) === 'false') {
                        $value = false;
                    }

                    // Handle as integer

                case 'int':
                    return (int)$value;

                default:
                    return $value;
            }
        }

        return $value;
    }


    protected function includeRelated($object, $inputData, &$fields)
    {
        if (isset($inputData['related']) && $related_objects = $inputData['related']) {
            // Loop through relationships
            foreach ($related_objects as $related_field => $related_object) {
                $object_relations = $object->relations();

                // Check if relationship
                if (isset($object_relations[$related_field])) {
                    $key_to = current($object_relations[$related_field]->key_to);

                    switch (get_class($object_relations[$related_field])) {
                        case 'Orm\HasMany':
                            // multiple children
                            $children = [];

                            foreach ($object->{$related_field} as $child) {
                                // var_dump($this->cleanObject($child, $related_object)); exit;
                                $children[] = $this->cleanObject($child, $related_object, [$key_to]);
                            }

                            $fields['include'][$related_field] = $children;
                            break;

                        default:
                            // default; singular relationship
                            $fields['include'][$related_field] = $this->cleanObject($object->{$related_field}, $related_object, [$key_to]);
                            break;
                    }

                    continue;
                }

                // Might be field name, try to find relationship for field
                foreach ($object_relations as $relation_name => $relation_properties) {
                    // Convert key to string, if relevant
                    // $key = implode('|', $relation_properties->key_from);
                    $key = current($relation_properties->key_from);
                    if ($key == $related_field) {
                        // Automatically remove 'Id' suffix by renaming
                        $without_id = preg_replace('/(.*?)Id$/i', '${1}', $related_field);
                        if ($without_id !== $related_field) {
                            $fields['rename'][$related_field] = $without_id;
                        }

                        if (isset($object->{$relation_name})) {
                            $object->{$related_field} = $this->cleanObject($object->{$relation_name}, $related_object);
                            $fields['remove'][] = $relation_name;
                        }
                    }
                }
            }
        }
    }


    protected function removeFields(&$array, $fields)
    {
        if (method_exists($this->model, 'temporal_properties') && ($temporalProperties = $this->model->temporal_properties())) {
            array_push($fields, $temporalProperties['start_column'], $temporalProperties['end_column']);
        }

        // Strip 'private' fields
        foreach ($fields as $field) {
            unset($array[$field]);
        }
    }


    protected function renameFields(&$array, $fields)
    {
        foreach ($fields as $name => $new_name) {
            if (isset($array[$name])) {
                $array[$new_name] = $array[$name];
                unset($array[$name]);
            }
        }
    }


    protected function includeFields(&$array, $fields)
    {
        foreach ($fields as $name => $value) {
            $array[$name] = $value;
        }
    }


    protected function filterFields($object, &$array, $inputData)
    {
        // Check if model defines fields to be exported
        $requiredFields = $object->apiFields();

        if (isset($inputData['fields'])) {
            // Use fields provided by user
            $requiredFields = $inputData['fields'];
        }

        // Check that requested fields exist in array
        foreach ($requiredFields as $field) {
            // If field is not set, but it exists as a method, call it
            if (! isset($array[$field]) && ($method = $object->apiMethod($field))) {
                $array[$field] = $object->{$method}();
            }
        }

        if ($requiredFields) {
            $array = array_filter($array, function ($key) use ($requiredFields) {
                return in_array($key, $requiredFields);
            }, ARRAY_FILTER_USE_KEY);
        }
    }


    protected function applyModifications(&$array, $inputData)
    {
        // Check for modifiers
        if (isset($inputData['modifiers']) && ($modifiers = $inputData['modifiers'])) {
            foreach ($modifiers as $modifyField => $modifications) {
                if (isset($array[$modifyField])) {
                    foreach ($modifications as $modificationType => $modificationType) {
                        switch ($modificationType) {
                            case 'rename':
                                $array[$modificationType] = $array[$modifyField];
                                unset($array[$modifyField]);
                                break;

                            default:
                                //default
                                break;
                        }
                    }
                }
            }
        }
    }
}
