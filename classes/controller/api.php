<?php

namespace Anstech\Crud\Controller;

use Anstech\Crud\Rest;
use Anstech\Rest\Controller\Cors;
use Fuel\Core\Config;
use Fuel\Core\Format;
use Fuel\Core\Input;
use Fuel\Core\Request;
use Fuel\Core\Response;

class Api extends Cors
{
    // Default format
    // protected $rest_format = 'json';

    /**
     * Status to return when there is no data
     *
     * @var int
     * @access protected
     */
    protected $no_data_status = 200;


    /**
     * Gets JSON request body, as array
     *
     * @return array
     */
    protected function inputData()
    {
        return Format::forge(file_get_contents('php://input'), 'json')->to_array();
    }


    /**
     * Parse input arguments to calculate resource
     *
     * @param string $resource
     * @param array $arguments
     *
     * @return void
     */
    protected function parseArguments(&$resource, &$arguments)
    {
        while (count($arguments)) {
            $argument = $arguments[0];
            if (is_string($argument) && ! is_numeric($argument) && ! in_array($argument, ['new', 'schema'])) {
                $resource .= '_' . array_shift($arguments);
            } else {
                break;
            }
        }
    }


    /**
     * Set REST format based on input content type
     *
     * @return void
     */
    protected function parseContentType()
    {
        if ($content_type = Input::server('HTTP_CONTENT_TYPE')) {
            switch ($content_type) {
                case 'application/json':
                    $this->rest_format = 'json';
                    break;

                case 'application/xml':
                case 'text/xml':
                    $this->rest_format = 'xml';
                    break;

                default:
                    // Unknown content type
                    break;
            }
        }
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
        // Parse argument in case they contain the model/resource (e.g. vehicle/colour -> vehicle_colour)
        // $this->parse_arguments($resource, $arguments);

        // Set default format based on Content-Type header by default
        $this->parseContentType();

        // Check that resource exists
        if (! ($model = Rest::forge($resource, get_called_class()))) {
            return $this->response(['message' => 'Resource not found'], 500);
        }

        // If no (or an invalid) format is given, auto detect the format
        if (is_null($this->format) or ! array_key_exists($this->format, $this->_supported_formats)) {
            // Auto-detect the format
            $this->format = array_key_exists(Input::extension(), $this->_supported_formats) ? Input::extension() : $this->_detect_format();
        }

        // Get the configured auth method if none is defined
        if ($this->auth === null) {
            $this->auth = Config::get('rest.auth');
        }

        // Check method is authorized if required, and if we're authorized
        $valid_login = false;
        if ($this->auth == 'basic') {
            $valid_login = $this->_prepare_basic_auth();
        } elseif ($this->auth == 'digest') {
            $valid_login = $this->_prepare_digest_auth();
        } elseif (method_exists($this, $this->auth)) {
            if (($valid_login = $this->{$this->auth}()) instanceof Response) {
                return $valid_login;
            }
        }

        // If the request passes auth then execute as normal
        if (empty($this->auth) or $valid_login) {
            // Specific object id
            $id = (isset($arguments[0]) ? $arguments[0] : null);

            // Special cases
            switch ($extension = strtolower(Input::extension())) {
                case 'schema':
                    return $model->getSchema();

                case '':
                    // Do nothing
                    break;

                default:
                    // List of field values
                    list($rows, $total_count) = $model->fieldValues($extension);
                    $this->response->set_header('Access-Control-Expose-Headers', 'x-total-count');
                    $this->response->set_header('x-total-count', $total_count);
                    return $rows;
            }

            // Action based on method
            switch (strtolower(Input::method())) {
                case 'get':
                    // Get one/many
                    if ($id) {
                        // Get one
                        return $model->getOne($id, $this->inputData());
                    }

                    // Get many
                    list($rows, $total_count) = $model->getMany($this->inputData());
                    $this->response->set_header('Access-Control-Expose-Headers', 'x-total-count');
                    $this->response->set_header('x-total-count', $total_count);
                    return $rows;

                case 'post':
                    // Create one
                    list($updated, $data) = $model->createOne($this->inputData());
                    if ($updated) {
                        return $this->response($data);
                    }
                    break;

                case 'patch':
                case 'put':
                    // Update one
                    list($updated, $data) = $model->updateOne($id, $this->inputData());
                    if ($updated) {
                        return $this->response($data);
                    }
                    break;

                case 'delete':
                    // Delete one
                    list($deleted, $data) = $model->deleteOne($id);
                    if ($deleted) {
                        return $this->response($data);
                    }
                    break;

                default:
                    return $this->response(['message' => 'Invalid method'], 500);
            }

            // Executed a method, but got an error
            return $this->response(['message' => $data], 500);
        }

        return $this->response(['status' => 0, 'error' => 'Not Authorized'], 401);
    }
}
