<?php

namespace Anstech\Crud\Form;

use Anstech\Template\Enqueue;
use Fuel\Core\Arr;
use Fuel\Core\Fieldset;
use Fuel\Core\Inflector;
use Parser\View;

class Base
{
    /**
     * Forge method to provide consistency with FuelPHP
     *
     * @return static
     */
    public static function forge()
    {
        return new static();
    }


    /**
     * Automatically adds related fields to object, based on ORM relations
     *
     * @param mixed $object
     * @param Fieldset $fieldset
     *
     * @return void
     */
    public static function addRelatedFields($object, $fieldset)
    {
        if ($relations = $object->relations()) {
            // Loop through relations
            foreach ($relations as $relation_name => $relation) {
                if (! $object->relationOptions($relation_name, 'crud_enabled', true)) {
                    continue;
                }

                // Check relation type/class
                switch (get_class($relation)) {
                    case 'Orm\HasMany':
                        // Define target model
                        $related_to = $relation->model_to::forge();

                        // Queue the relevant plugin for selects
                        Enqueue::enqueuePlugins('selectr');

                        // Define field properties
                        $field_name = $relation_name; //substr($relation_name, 1);
                        $field_label = $related_to->getTitle(true);
                        $field_properties = [
                            'class'    => 'form-control selectr',
                            'multiple' => true,
                            'options'  => $related_to->options(),
                            'type'     => 'select',
                        ];
                        $field_rules = [];

                        // Check whether to insert after a particular field
                        if ($add_after = $object->relationOptions($relation_name, 'add_after')) {
                            $fieldset->add_after($field_name, $field_label, $field_properties, $field_rules, $add_after);
                        } else {
                            // Insert field at end
                            $fieldset->add($field_name, $field_label, $field_properties, $field_rules);
                        }

                        // Set current value(s)
                        $object->{ $field_name} = $object->relatedValues($relation_name);
                        break;

                    default:
                        // Relationship not relevant
                        break;
                }
            }
        }
    }


    /**
     * Returns array of alerts, or false if non exist
     *
     * @param bool $saved
     * @param array $errors
     *
     * @return array|bool
     */
    protected static function alerts($saved, $errors)
    {
        if ($saved || $errors) {
            if ($saved) {
                return ['hasSaved' => true];
            } else {
                // Convert errors to strings
                $error_messages = [];
                foreach ($errors as $error) {
                    $error_messages = $error->get_message();
                }

                return [
                    'hasErrors' => true,
                    'errors'    => $error_messages,
                ];
            }
        }

        return false;
    }


    /**
     * Helper function to process customisations to Fieldset class
     *
     * @param Fieldset $fieldset
     * @param array $tabs
     * @param mixed $instance
     *
     * @return void
     */
    public static function helper($fieldset, &$tabs = [], $instance = null)
    {
        $form = $fieldset->form();

        // Add the csrf token field to the form
        $form->add_csrf();

        // Customise the default template
        $fieldset->set_config('form_template', View::forge('form/basic/form.mustache')->render());
        $fieldset->set_config('field_template', View::forge('form/basic/fields/default.mustache')->render());
        $fieldset->set_config('multi_field_template', View::forge('form/basic/fields/multifield.mustache')->render());

        // Apply valid bootstrap class to inputs & set validation rules to HTML tag rules
        foreach ($form->field() as $field) {
            if ($field->type === 'text') {
                $field->set_attribute('class', $form->get_config('input_class', 'form-control'));

                foreach ($field->rules as $rule) {
                    if ($value = (int)reset($rule[1])) {
                        $rule[0] === 'max_length' && $field->set_attribute('maxlength', $value);
                        $rule[0] === 'min_length' && $field->set_attribute('minlength', $value);
                    }
                }
            } elseif ($field->type === 'checkbox') {
                switch ($field->get_attribute('sub_type')) {
                    case 'switch':
                        $field->set_template(View::forge('form/basic/switch.mustache')->render());
                        break;

                    default:
                        // Do nothing
                        break;
                }
            }

            // Workaround for lack of description template.  Since we don't want <p></p> to be visible on fields without description
            if (trim($field->description) !== '') {
                $field->set_description('<p class="help-block">' . $field->description . '</p>');
            }

            // Group fields into tabs
            if ($tab = $field->get_attribute('tab', 'default')) {
                // Convert string only tab to array
                if (is_string($tab)) {
                    $tab = ['name' => $tab];
                }

                // Read tab name, or default
                $tab_name = Arr::get($tab, 'name', 'default');
                if (! isset($tabs[$tab_name])) {
                    $tabs[$tab_name] = [
                        'name'        => Inflector::underscore($tab_name),
                        'description' => $tab_name,
                        'fields'      => [],
                        'sort_order'  => Arr::get($tab, 'sort_order', ($tab_name === 'default' ? 0 : 1)),
                    ];
                }

                // Add field to tab and clear tab attribute
                $tabs[$tab_name]['fields'][] = $field->name;
                $field->set_attribute('tab', null);
            }
        }
    }


    /**
     * Generate form based on object
     *
     * @param mixed $object
     * @param bool $saved
     * @param array $errors
     *
     * @return object
     */
    public function render($object, $saved = false, $errors = [])
    {
        // Queue plugins associated with form
        Enqueue::enqueuePlugins('crud-form');

        // Create fieldset(s)
        $fieldset = Fieldset::forge('form');
        $fieldset->add_model($object);

        // Add related field(s)
        static::addRelatedFields($object, $fieldset);

        // Tabbed form
        $tabs = [];

        // Modify fieldset
        static::helper($fieldset, $tabs);
        $fieldset->populate($object);

        return View::forge('form/basic.mustache', [
            'alerts'  => static::alerts($saved, $errors),
            'hasTabs' => count($tabs) > 1 ? true : false,
            'tabs'    => $this->tabs($object, $fieldset, $tabs),
        ], false);
    }


    /**
     * Builds tab pane content for each tab
     *
     * @param mixed $object
     * @param Fieldset $fieldset
     * @param array $tabs
     *
     * @return array
     */
    public function tabs($object, $fieldset, $tabs)
    {
        // Fields
        $fields = $fieldset->form()->field();

        uasort($tabs, function ($a, $b) {
            return ($a['sort_order'] - $b['sort_order']);
        });

        // Loop through tabs
        $activeTab = false;
        foreach ($tabs as $tabName => &$tabDetails) {
            // Check if active
            if (Arr::get($tabDetails, 'active')) {
                $activeTab = $tabName;
            }

            // Change default to title
            if ($tabName === 'default') {
                $tabDetails['description'] = $object->getTitle();
            }

            // Enable relevant fields
            foreach ($fields as $field) {
                if (in_array($field->name, $tabDetails['fields'])) {
                    $fieldset->enable($field->name);
                } else {
                    $fieldset->disable($field->name);
                }
            }

            // Build tab content
            $tabDetails['content'] = $fieldset->build();
        }

        // Removed named keys
        $tabs = array_values($tabs);

        // Check for active tab
        if (! $activeTab) {
            // Set first tab as active by default
            $tabs[0]['active'] = true;
        }

        return $tabs;
    }
}
