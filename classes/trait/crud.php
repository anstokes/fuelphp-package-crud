<?php

namespace Anstech\Crud\Trait;

use Fuel\Core\Arr;
use Fuel\Core\Inflector;
use Fuel\Core\Validation;

trait Crud
{
    /**
     * Array of fields which can be accessed via the API
     * @var array
     * @access protected
     */
    protected $api_fields = [];

    /**
     * Array of class methods which can be called by the API as if they are fields
     * fakeField => methodName
     * @var array
     * @access protected
     */
    protected $api_methods = [];

    /**
     * The field(s) which label the model
     * These fields are used to generate the label for each specific object
     * @var array
     * @access protected
     */
    protected $labelled_by_fields = [];

    /**
     * Associative array of key => label for models
     * @var array
     * @access protected
     */
    protected $options = null;

    protected $relations_options = [];

    /**
     * Title of the model
     * The generic title for all objects in this model
     * @var string
     * @access protected
     */
    protected $title = null;


    /**
     * Returns array of fields exposed via the API
     * If not explicitly configured all fields are exposed by default
     *
     * @return array
     */
    public function apiFields()
    {
        if ($this->api_fields) {
            return $this->api_fields;
        }

        return array_keys($this->properties());
    }


    public function apiMethod($field)
    {
        return Arr::get($this->api_methods, $field);
    }


    /**
     * Returns the controller URI
     *
     * @param string $name Controller class name
     * @return string
     */
    public function controllerUri()
    {
        $class_name = get_called_class();
        $class = str_replace(['Entity_', 'Entity\\', 'Model_', 'Model\\'], '', $class_name);
        return '/' . strtolower($class);

        /*
        $module = trim(Inflector::get_namespace($class_name), '\\');
        $class = str_replace(['Entity_', 'Entity\\', 'Model_', 'Model\\'] , '', Inflector::denamespace($class_name));
        return '/' . strtolower($module . '/' . $class);
        */
    }


    /**
     * CamelCase alias of get_id
     *
     * @return bool
     */
    public function getId()
    {
        return $this->get_id();
    }


    /**
     * Returns the IRI for a specific object
     *
     * @param string $type
     * @return string
     */
    public function getIri($type = 'edit')
    {
        $controller_uri = $this->controllerUri();
        return $controller_uri . '/' . $type . '/' . $this->getId();
    }


    /**
     * Returns the label for a specific object
     * i.e. the value to use for this item in an option list
     *
     * @return string
     */
    public function getLabel()
    {
        $labels = [];

        // Check if label fields are defined
        if (! $this->labelled_by_fields) {
            $title_column = strtolower($this->getTitle());
            if (property_exists($this, '_labelled_by_fields')) {
                // Use static '_labelled_by_fields' property
                $this->labelled_by_fields = static::$_labelled_by_fields;
            } elseif (Arr::key_exists($this->properties(), $title_column)) {
                // Attempt to calculate label fields from title
                $this->labelled_by_fields = [$title_column];
            } elseif (Arr::key_exists($this->properties(), 'description')) {
                // Or use description field, if available
                $this->labelled_by_fields = ['description'];
            } else {
                // If all else fails, use primary key
                $this->labelled_by_fields = [static::singularPrimaryKey()];
            }
        }

        // Ensure array, in case a string was provided
        if (is_string($this->labelled_by_fields)) {
            $this->labelled_by_fields = [$this->labelled_by_fields];
        }

        // Create an array of labels
        foreach ($this->labelled_by_fields as $label_field) {
            $labels[] = $this->{$label_field};
        }

        // Merge labels to string
        return implode(' ', $labels);
    }


    /**
     * Returns the title of the model
     *
     * @return string
     */
    public function getTitle($plural = false)
    {
        if (! $this->title) {
            // Guess title if not provided
            $class_name = Inflector::denamespace(get_called_class());

            // Handle underscores in default FuelPHP naming structure
            $last_underscore = strrpos($class_name, '_');
            if ($last_underscore !== false) {
                $class_name = substr($class_name, ($last_underscore + 1));
            }

            $this->title = ucwords(Inflector::humanize($class_name));
        }

        return ($plural ? Inflector::pluralize($this->title) : $this->title);
    }


    /**
     * CamelCase alias of is_new
     *
     * @return bool
     */
    public function isNew()
    {
        return $this->is_new();
    }


    /**
     * Returns an associative array of the models; key => label
     *
     * @param bool $refresh
     * @return array
     */
    public function options($refresh = false)
    {
        $class = get_called_class();
        if ($refresh || ! isset($this->options[$class])) {
            $primary_key = static::singularPrimaryKey();

            $options = [];
            $objects = static::query()->get();

            foreach ($objects as $object) {
                $options[$object->{$primary_key}] = $object->getLabel();
            }

            $this->options[$class] = $options;
        }

        return $this->options[$class];
    }


    /**
     * Find relation option(s)
     *
     * @param string    $relation_name
     * @param string    $option
     * @param mixed     $default
     *
     * @return mixed
     */
    public function relationOptions($relation_name, $option = false, $default = null)
    {
        if (! $option) {
            // Return all options
            return Arr::get($this->relations_options, $relation_name, $default);
        }

        // Return specific option
        return Arr::get($this->relations_options, $relation_name . '.' . $option, $default);
    }


    public function relationProperties($relation_name, $relation)
    {
        // var_dump($relation); exit;

        // Check for relation options
        if ($relations_options = Arr::get($this->relations_options, $relation_name)) {
            $relation_visible = Arr::get($relations_options, 'visible', null);
            $relation_hidden = Arr::get($relations_options, 'hidden', null);

            // Check if hidden
            if (($relation_visible === false) || ($relation_hidden === true)) {
                return [];
            }
        }

        $plural = (get_class($relation) === 'Orm\HasMany');
        $related_to = $relation->model_to::forge();

        return [
            'label'         => $related_to->getTitle($plural),
            'relation_type' => lcfirst(Inflector::denamespace(get_class($relation))),
        ];
    }


    /**
     * Related values
     *
     * @param string $relation Relationship name
     *
     * @return array
     */
    public function relatedValues($relation)
    {
        $array = [];
        $relations = $this->relations();

        if (isset($relations[$relation]) && $relations[$relation]) {
            // Related to
            $related_to = $relations[$relation]->model_to;
            $related_to_relations = $related_to::relations();
            $is_nested_relation = isset($related_to_relations[substr($relation, 0, -1)]) ? true : false;
            $source_field = ($is_nested_relation ? $related_to_relations[substr($relation, 0, -1)]->key_from[0] : 'id');

            // Loop through relations
            foreach ($this->{$relation} as $related_object) {
                $array[] = $related_object->{$source_field};
            }
        }

        return $array;
    }


    /**
     * Returns a single field/value primary key
     *
     * @return string
     */
    public static function singularPrimaryKey()
    {
        if (is_array(static::primary_key())) {
            $primary_keys = static::primary_key();
            return reset($primary_keys);
        }

        return static::primary_key();
    }


    /**
     * Method to update an object
     *
     * @param array $configuration  Array of properties to update object
     * @param bool  $save           Whether to commit/save changes
     *
     * @return array
     */
    public function crudUpdate($configuration, $save = false)
    {
        // Check if existing
        if (isset($configuration['id'])) {
            // Configuration should match existing object
            if ($configuration['id'] !== $this->id) {
                // Mismatched objects
                return [
                    false,                                  // Error
                    ['Mismatched configuration'],           // Message(s)
                    false,                                  // Saved
                ];
            }
        }

        // Unset id; it cannot be changed
        unset($configuration['id']);

        // Fetch fields
        $fields = array_keys($this->properties());

        // Make sure we always have certain elements, and set defaults where not provided
        $this->applyDefaults($configuration);

        // Get additional fields (from customisations)
        if ($custom_fields = $this->customFields($configuration)) {
            // Add additional fields (from customisations)
            $fields = array_merge($fields, $custom_fields);
        }

        // Find relations
        $relations = $this->relations();

        // Set variables
        foreach ($configuration as $field_name => $field_value) {
            // Ensure field exists in object
            if (in_array($field_name, $fields)) {
                $this->{$field_name} = $field_value;
            } elseif (is_array($field_value) && isset($relations['_' . $field_name])) {
                $this->save_related($field_name, $field_value, $relations);
            }
        }

        // TODO - Check if adding new sub-objects
        /*
        $addNew = \Input::post('addNew', false);
        if ($addNew && ($addNew == "role")) {
            $component->staffRoles[] = Model_ComponentStaffRoles::forge();
        }
        */

        // Check if actually saving - commit changes
        if ($save) {
            // Check if object would validate
            $validate_component = Validation::forge(get_called_class())->add_model($this);
            if ($validate_component && ! $validate_component->run($configuration)) {
                // Add validation error to interface, if appropriate
                return [
                    false,                          // Error
                    $validate_component->error(),   // Message(s)
                    false,                          // Saved
                ];
            } else {
                // Save object
                $this->save();

                // Fetch/refresh existing object
                static::flush_cache();
                $object = static::query()
                            ->where('id', $this->id)
                            ->get_one();

                return [
                    true,       // Success
                    $object,    // Updated object
                    true,       // Saved
                ];
            }
        }

        return [
            true,       // Success
            $this,      // Object
            false,      // Saved
        ];
    }


    /**
     * Set defaults on object, where input not provided
     *
     * @param mixed $input
     *
     * @return void
     */
    protected function applyDefaults($input)
    {
        foreach ($this->properties() as $key => $properties) {
            if (! isset($input[$key]) && isset($properties['default'])) {
                $this->{$key} = $properties['default'];
            }
        }
    }


    /**
     * Find custom fields for object
     *
     * @param array $input
     *
     * @return array
     */
    protected function customFields(&$input)
    {
        // $custom_fields = array_keys(\Model_CustomisationArea::customised_fields(static::customisation_areas()));
        $custom_fields = [];

        // Deal with unsetting of values from customisations (e.g. checkboxes)
        foreach ($custom_fields as $custom_field) {
            if (isset($this->{$custom_field}) && ! isset($input[$custom_field])) {
                $input[$custom_field] = false;
            }
        }

        return $custom_fields;
    }


    /**
     * Save input value on related object
     *
     * @param string    $field_name     Relation name
     * @param mixed     $field_value
     * @param mixed     $relations      Array of relations
     *
     * @return void
     */
    protected function saveRelated($field_name, $field_value, $relations)
    {
        // Save related
        $relation = $relations['_' . $field_name];
        switch (get_class($relation)) {
            case 'Orm\HasMany':
                $related_object = $relation->model_to;
                $key_from = $relation->key_from[0];

                // Loop through current relations
                $current_values = [];
                foreach ($this->{'_' . $field_name} as $key => $related_object) {
                    if (in_array($related_object->{$key_from}, $field_value)) {
                        // echo 'Retain value: '.$related_object->{$key_from};
                        $current_values[] = $related_object->{$key_from};
                    } else {
                        // echo 'Remove value: '.$related_object->{$key_from};
                        unset($this->{'_' . $field_name}[$key]);
                    }
                }

                $new_values = array_diff($field_value, $current_values);
                foreach ($new_values as $new_value) {
                    // echo 'Add value: '.$new_value;
                    if ($existing_object = $related_object::find($new_value)) {
                        $this->{'_' . $field_name}[] = $existing_object;
                    }

                    // $related_object::forge([$related_field => $new_value]);
                }
                break;

            default:
                // Irrelevant relationship
                break;
        }
    }
}
