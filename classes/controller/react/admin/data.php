<?php

namespace Anstech\Crud\Controller\React\Admin;

use Anstech\Crud\Rest;
use Anstech\Rest\Controller\Cors;
use Fuel\Core\Config;
use Fuel\Core\Format;
use Fuel\Core\Input;
use Fuel\Core\Request;
use Fuel\Core\Response;

/**
 * This data provider fits REST APIs using simple GET parameters for filters and sorting.
 * https://github.com/marmelab/react-admin/tree/master/packages/ra-data-simple-rest
 */

class Data extends Cors
{
    // protected $rest_format = 'json';

    /**
     * Status to return when no data is available
     *
     * @var integer
     */
    protected $no_data_status = 200;

    /**
     * Returns JSON input as array
     *
     * @return array
     */
    protected function inputData()
    {
        return Format::forge(file_get_contents('php://input'), 'json')->to_array();
    }

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

    public function router($resource, $arguments)
    {
        // Check for CORS Preflight request
        if (Request::active()->get_method() == 'OPTIONS') {
            return $this->corsPreflight();
        }

        // Read REST configuration
        Config::load('rest', true);

        // Parse argument in case they contain the model/resource (e.g. vehicle/colour -> vehicle_colour)
        $this->parseArguments($resource, $arguments);

        // Check that resource exists
        if (! $model = Rest::forge($resource, get_called_class())) {
            return $this->response(['message' => 'Resource not found'], 500);
        }

        // If no (or an invalid) format is given, auto detect the format
        if (is_null($this->format) or ! array_key_exists($this->format, $this->_supported_formats)) {
            // Auto-detect the format
            $this->format = array_key_exists(Input::extension(), $this->_supported_formats) ? Input::extension() : $this->_detect_format();
        }

        // Get the configured auth method if none is defined
        $this->auth === null and $this->auth = Config::get('rest.auth');

        // Check method is authorized if required, and if we're authorized
        if ($this->auth == 'basic') {
            $valid_login = $this->_prepare_basic_auth();
        } elseif ($this->auth == 'digest') {
            $valid_login = $this->_prepare_digest_auth();
        } elseif (method_exists($this, $this->auth)) {
            if (($valid_login = $this->{$this->auth}()) instanceof Response) {
                return $valid_login;
            }
        } else {
            $valid_login = false;
        }

        // If the request passes auth then execute as normal
        if (empty($this->auth) or $valid_login) {
            // Specific object id
            $id = (isset($arguments[0]) ? $arguments[0] : null);

            // Special cases
            switch ($extension = strtolower(Input::extension() || '')) {
                case 'schema':
                    // Fetch schema from model
                    if (is_callable([$model, 'getSchema'])) {
                        return $model->getSchema();
                    }
                    // No schema available
                    return null;

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
                    return $this->response(['message' => $data], 500);

                case 'put':
                    // Update one
                    list($updated, $data) = $model->updateOne($id, $this->inputData());
                    if ($updated) {
                        return $this->response($data);
                    }
                    return $this->response(['message' => $data], 500);

                case 'delete':
                    // Delete one
                    list($deleted, $data) = $model->deleteOne($id);
                    if ($deleted) {
                        return $this->response($data);
                    }
                    return $this->response(['message' => $data], 500);

                default:
                    return $this->response(['message' => 'Invalid method'], 500);
            }
        } else {
            $this->response(['status' => 0, 'error' => 'Not Authorized'], 401);
        }
    }
}
