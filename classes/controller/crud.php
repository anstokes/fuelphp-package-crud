<?php

namespace Anstech\Crud\Controller;

use Fuel\Core\FuelException;
use Fuel\Core\HttpNotFoundException;
use Fuel\Core\Inflector;
use Fuel\Core\Input;
use Fuel\Core\Package;
use Fuel\Core\Request;
use Fuel\Core\Uri;
use Parser\View;

trait Crud
{
    /**
     * The model class
     * NOTE: guessed if not provided
     *
     * @var mixed
     * @access protected
     */
    protected static $model_class = null;

    /**
     * The class used to control the tabular display (list view)
     * NOTE: guessed if not provided
     *
     * @var string
     * @access protected
     */
    protected static $table_class = null;

    /**
     * The default table class, if everything else fails
     *
     * @var mixed
     * @access protected
     */
    protected static $default_table_class = '\Anstech\Crud\Table\Base';

    /**
     * The class used to control the form display (create/edit view)
     * NOTE: guessed if not provided
     *
     * @var string
     * @access protected
     */
    protected static $form_class = null;

    /**
     * The default form class, if everything else fails
     *
     * @var string
     * @access protected
     */
    protected static $default_form_class = '\Anstech\Crud\Form\Base';

    /**
     * The title to be used
     * NOTE: guessed if not provided
     *
     * @var string
     * @access protected
     */
    protected $title = null;

    /**
     * List of possible view extensions
     *
     * @var array
     * @access protected
     */
    protected static $view_extensions = [
        'mustache',
        'php',
    ];


    public function __construct(Request $request)
    {
        $class_name = get_called_class();

        // Guess (populate) model class, if not provided
        if (! static::$model_class) {
            static::$model_class = '\\' . str_replace('Controller\\', 'Model\\', $class_name);
        }

        // Guess (populate) table class, if not provided
        if (! static::$table_class) {
            static::$table_class = '\\' . str_replace('Controller\\', 'Table\\', $class_name);
        }

        // Guess (populate) form class, if not provided
        if (! static::$form_class) {
            static::$form_class = '\\' . str_replace('Controller\\', 'Form\\', $class_name);
        }

        // Guess title, if not provided
        if (! $this->title && $this->model()) {
            $this->title = $this->model()->getTitle(true);
        }

        // Call parent constructor
        parent::__construct($request);
    }


    /**
     * Summary of router
     *
     * @param string $resource
     * @param array $arguments
     *
     * @return mixed
     */
    public function router($resource, $arguments)
    {
        // Catch API requests
        if ($resource === 'api') {
            // Pass API requests through API controller
            $apiController = new Api(Request::active());
            $apiController->before();
            return $apiController->after($apiController->router(static::$model_class, $arguments));
        }

        // The following logic is fundamentally borrowed from FuelPHP request class...

        // Load the controller using reflection
        $class = new \ReflectionClass(get_called_class());

        // Use HTTP request method (get, post, patch) as action prefix
        $method = strtolower(Input::method()) . '_' . $resource;
        if (! $class->hasMethod($method)) {
            // Fall back to action_ if no HTTP request method based method exists
            $method = 'action_' . $resource;
        }

        // Check that action exists and is publically accessible
        if ($class->hasMethod($method) && ($action = $class->getMethod($method)) && $action->isPublic()) {
            return call_user_func_array([$this, $method], $arguments);
        }

        throw new HttpNotFoundException();
    }


    /**
     * Returns the controller's views directory
     *
     * @param string $name Controller class name
     *
     * @return string
     */
    protected static function controllerViewsDirectory($name = null)
    {
        $namespace = trim(Inflector::get_namespace(get_called_class()), '\\');
        return strtolower($namespace . '::' . $name);
    }


    /**
     * Returns name of associated model class
     *
     * @return null|string
     */
    protected function modelClass()
    {
        return static::$model_class;
    }


    /**
     * Returns an instance of the associated model
     *
     * @return object
     */
    protected function model()
    {
        return static::$model_class::forge();
    }


    /**
     * Returns an instance of the associated table
     *
     * @return object
     */
    protected function table()
    {
        // Default table class, if provided table class not available
        if (! class_exists(static::$table_class)) {
            static::$table_class = static::$default_table_class;
        }

        // Return table
        return static::$table_class::forge($this->model());
    }


    /**
     * Returns an instance of the associated form
     *
     * @return object
     */
    protected function form()
    {
        // Default form class, if provided form class not available
        if (! class_exists(static::$form_class)) {
            static::$form_class = static::$default_form_class;
        }

        // Return form
        return static::$form_class::forge($this->model());
    }



    /**
     * Get the title
     *
     * @return string
     */
    protected function getTitle()
    {
        return $this->title;
    }


    /**
     * Set the title
     *
     * @param string $title
     *
     * @return Crud
     */
    protected function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * Default action
     */
    public function action_index()
    {
        // Show existing records in table
        $this->action_list();
    }


    /**
     * Table view / list of objects
     */
    public function action_list()
    {
        // Show the objects
        $this->template->title = $this->getTitle();
        $this->template->content = $this->table()->render();
    }


    /**
     * New action
     */
    public function action_new()
    {
        // Handled by edit action
        $this->action_edit(true);
    }


    /**
     * Edit action, may be overridden / extended
     *
     * @param bool $new
     */
    public function action_edit($new = false)
    {
        // Defaults
        $errors = [];
        $saved = false;
        $model_class = $this->modelClass();

        // Read input variables
        $configuration = Input::post('configuration');
        $save = Input::post('save');

        // Read URI segments
        $segments = Uri::segments();
        $object_id = array_pop($segments);

        // Ensure pen-ultimate segment is 'edit'
        if (! array_pop($segments) === 'edit') {
            unset($object_id);
        }

        // Check if creating new object
        if (($new === true) || ($object_id === 'new')) {
            // Create new object
            $object = $model_class::forge();
        } else {
            // Fetch existing object
            $object = $model_class::query()
                        ->where('id', $object_id)
                        ->get_one();
        }

        if (! $object && ! $configuration) {
            // Could not load model; select again
            $this->action_list();
            return;
        }

        // Check if model data passed; adjust model
        if ($configuration) {
            // Update model
            list($success, $detail, $saved) = $object->crudUpdate($configuration, $save);
            if ($success) {
                // Detail is the updated object
                $object = $detail;
            } else {
                // Detail is the errors
                $errors = $detail;
            }
        }

        $object_type = ltrim(str_replace('\\', '/', preg_replace('/\\\model\\\/', '/', strtolower($model_class))), '/');

        // View data
        $data = [
            'objectType' => $object_type,
            'id'         => $object->id ?: 'new',
            'isNew'      => $object->isNew(),
            'hasHistory' => false,
            'title'      => $object->getTitle(),
            'titles'     => $object->getTitle(true),
            'label'      => $object->getLabel(),
            'form'       => $this->form()->render($object, $saved, $errors),
        ];

        // Update Breadcrumb
        if (Package::loaded('template')) {
            \Anstech\Breadcrumb::set($object->getTitle(true), Uri::base() . implode('/', $segments));
            \Anstech\Breadcrumb::set($object->isNew() ? 'New ' . $object->getTitle() : $object->getLabel());
        }

        // Make information global (available across nested views)
        $this->template->set_global('object', $object, false);
        foreach ($data as $variable_name => $variable_content) {
            $this->template->set_global($variable_name, $variable_content, false);
        }

        // Configure view
        $this->template->title = $this->getTitle();
        $this->template->content = $this->editView();
    }


    /**
     * View for the edit action
     */
    protected function editView()
    {
        // Check view exists
        $edit_view = $this->controllerViewsDirectory() . '/edit';
        foreach (static::$view_extensions as $view_extension) {
            try {
                // Create view
                return View::forge($edit_view . ($view_extension === 'php' ? '' : '.' . $view_extension));
            } catch (FuelException $e) {
                // View wasn't found
            }
        }

        // View not found, fallback to default
        // return View::forge('edit/refresh.mustache');
        return View::forge('edit/api.mustache');
    }
}
