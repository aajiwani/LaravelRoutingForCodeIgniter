<?php
/**
 * User: Amirali
 * Date: 4/19/2016
 * Time: 1:38 PM
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Router Class
 *
 * Parses URIs and determines routing
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/user_guide/general/routing.html
 */

class CI_Router
{

    /**
     * CI_Config class object
     *
     * @var	object
     */
    public $config;

    /**
     * List of routes
     *
     * @var	array
     */
    public $routes  =   array();

    /**
     * Current class name
     *
     * @var	string
     */
    public $class   =   '';

    /**
     * Current method name
     *
     * @var	string
     */
    public $method  =   'index';

    /**
     * Named parameters list associated with route
     *
     * @var array
     */
    private $named_params    =   [];

    /**
     * Named parameters list as of function definition
     *
     * @var array
     */
    private $named_params_func  =   [];

    /**
     * Sub-directory that contains the requested controller class
     *
     * @var	string
     */
    public $directory;

    /**
     * Default controller (and method if specific)
     *
     * @var	string
     */
    public $default_controller;

    /**
     * Translate URI dashes
     *
     * Determines whether dashes in controller & method segments
     * should be automatically replaced by underscores.
     *
     * @var	bool
     */
    public $translate_uri_dashes = FALSE;

    /**
     * Enable query strings flag
     *
     * Determines whether to use GET parameters or segment URIs
     *
     * @var	bool
     */
    public $enable_query_strings = FALSE;

    // --------------------------------------------------------------------

    /**
     * Class constructor
     *
     * Runs the route mapping function.
     *
     * @param	array	$routing
     * @return	void
     */
    public function __construct($routing = NULL)
    {
        $this->config =& load_class('Config', 'core');
        $this->uri =& load_class('URI', 'core');

        $this->enable_query_strings = ( ! is_cli() && $this->config->item('enable_query_strings') === TRUE);

        // If a directory override is configured, it has to be set before any dynamic routing logic
        is_array($routing) && isset($routing['directory']) && $this->set_directory($routing['directory']);
        $this->_set_routing();

        // Set any routing overrides that may exist in the main index file
        if (is_array($routing))
        {
            empty($routing['controller']) OR $this->set_class($routing['controller']);
            empty($routing['function'])   OR $this->set_method($routing['function']);
        }

        log_message('info', 'Router Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
     * Set route mapping
     *
     * Determines what should be served based on the URI request,
     * as well as any "routes" that have been set in the routing config file.
     *
     * @return	void
     */
    protected function _set_routing()
    {
        if (!(isset($route_config) && is_array($route_config)))
        {
            $route_config = [];
        }

        if (!(isset($routes) && is_array($routes)))
        {
            $routes = [];
        }

        // Load the routes.php file. It would be great if we could
        // skip this for enable_query_strings = TRUE, but then
        // default_controller would be empty ...
        if (file_exists(APPPATH.'config/routes.php'))
        {
            include(APPPATH.'config/routes.php');
        }

        if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/routes.php'))
        {
            include(APPPATH.'config/'.ENVIRONMENT.'/routes.php');
        }

        // Validate & get reserved routes
        if (isset($route_config) && is_array($route_config))
        {
            isset($route_config['default_controller']) && $this->default_controller = $route_config['default_controller'];
            isset($route_config['translate_uri_dashes']) && $this->translate_uri_dashes = $route_config['translate_uri_dashes'];
        }

        if (isset($routes) && is_array($routes))
        {
            $this->routes = $routes;
        }

        // Are query strings enabled in the config file? Normally CI doesn't utilize query strings
        // since URI segments are more search-engine friendly, but they can optionally be used.
        // If this feature is enabled, we will gather the directory/class/method a little differently
        if ($this->enable_query_strings)
        {
            // If the directory is set at this time, it means an override exists, so skip the checks
            if ( ! isset($this->directory))
            {
                $_d = $this->config->item('directory_trigger');
                $_d = isset($_GET[$_d]) ? trim($_GET[$_d], " \t\n\r\0\x0B/") : '';

                if ($_d !== '')
                {
                    $this->uri->filter_uri($_d);
                    $this->set_directory($_d);
                }
            }

            $_c = trim($this->config->item('controller_trigger'));
            if ( ! empty($_GET[$_c]))
            {
                $this->uri->filter_uri($_GET[$_c]);
                $this->set_class($_GET[$_c]);

                $_f = trim($this->config->item('function_trigger'));
                if ( ! empty($_GET[$_f]))
                {
                    $this->uri->filter_uri($_GET[$_f]);
                    $this->set_method($_GET[$_f]);
                }

                $this->uri->rsegments = array(
                    1 => $this->class,
                    2 => $this->method
                );
            }
            else
            {
                $this->_set_default_controller();
            }

            // Routing rules don't apply to query strings and we don't need to detect
            // directories, so we're done here
            return;
        }

        // Is there anything to parse?
        if ($this->uri->uri_string !== '')
        {
            $this->_parse_routes();
        }
        else
        {
            $this->_set_default_controller();
        }
    }

    // --------------------------------------------------------------------

    /*
     * Verify parameters set in the routes with names and types
     *
     *
     * @used-by CodeIgniter
     * @return  bool
     */
    public function verify_parameters()
    {
        $prmList = $this->named_params;
        $result = true;
        if (!empty($prmList))
        {
            $funcArgs = [];
            $routeArgs = [];

            $reflection = new ReflectionMethod($this->class, $this->method);

            foreach($reflection->getParameters() AS $arg)
            {
                $funcArgs[] = $arg->getName();
            }

            foreach ($prmList as $prm)
            {
                $routeArgs[] = $prm;
            }

            $contains = count(array_intersect($routeArgs, $funcArgs)) == count($routeArgs);
            if ($contains)
            {
                $this->named_params_func = $funcArgs;
            }
            else
            {
                $result = false;
            }
        }

        return $result;
    }

    // --------------------------------------------------------------------

    /*
     * Get named parameters in the route
     *
     *
     * @used-by CodeIgniter
     * @return  string
     */
    public function get_current_route_named_params()
    {
        return implode(',', $this->named_params);
    }

    // --------------------------------------------------------------------

    /*
     * Returns given parameters with respect to function param arrangement, as per names of the params
     *
     *
     * @used-by CodeIgniter
     * @return  array
     */
    public function get_sorted_params($routeParamValues)
    {
        $paramsWithValues = [];
        for ($i = 0; $i < count($routeParamValues); $i++)
        {
            $paramsWithValues[$this->named_params[$i]] = $routeParamValues[$i];
        }

        $sortedParams = [];
        foreach ($this->named_params_func as $funcParamName)
        {
            $sortedParams[$funcParamName] = $paramsWithValues[$funcParamName];
        }

        return $sortedParams;
    }

    // --------------------------------------------------------------------

    /**
     * Set request route
     *
     * Takes an array of URI segments as input and sets the class/method
     * to be called.
     *
     * @used-by	CI_Router::_parse_routes()
     * @param	array	$segments	URI segments
     * @return	void
     */
    protected function _set_request($segments = array())
    {
        $segments = $this->_validate_request($segments);
        // If we don't have any segments left - try the default controller;
        // WARNING: Directories get shifted out of the segments array!
        if (empty($segments))
        {
            $this->_set_default_controller();
            return;
        }

        if ($this->translate_uri_dashes === TRUE)
        {
            $segments[0] = str_replace('-', '_', $segments[0]);
            if (isset($segments[1]))
            {
                $segments[1] = str_replace('-', '_', $segments[1]);
            }
        }

        $this->set_class($segments[0]);
        if (isset($segments[1]))
        {
            $this->set_method($segments[1]);
        }
        else
        {
            $segments[1] = 'index';
        }

        array_unshift($segments, NULL);
        unset($segments[0]);
        $this->uri->rsegments = $segments;
    }

    // --------------------------------------------------------------------

    /**
     * Set default controller
     *
     * @return	void
     */
    protected function _set_default_controller()
    {
        if (empty($this->default_controller))
        {
            show_error('Unable to determine what should be displayed. A default route has not been specified in the routing file.');
        }

        // Is the method being specified?
        if (sscanf($this->default_controller, '%[^/]/%s', $class, $method) !== 2)
        {
            $method = 'index';
        }

        if ( ! file_exists(APPPATH.'controllers/'.$this->directory.ucfirst($class).'.php'))
        {
            // This will trigger 404 later
            return;
        }

        $this->set_class($class);
        $this->set_method($method);

        // Assign routed segments, index starting from 1
        $this->uri->rsegments = array(
            1 => $class,
            2 => $method
        );

        log_message('debug', 'No URI present. Default controller set.');
    }

    // --------------------------------------------------------------------

    /**
     * Validate request
     *
     * Attempts validate the URI request and determine the controller path.
     *
     * @used-by	CI_Router::_set_request()
     * @param	array	$segments	URI segments
     * @return	mixed	URI segments
     */
    protected function _validate_request($segments)
    {
        $c = count($segments);
        $directory_override = isset($this->directory);

        // Loop through our segments and return as soon as a controller
        // is found or when such a directory doesn't exist
        while ($c-- > 0)
        {
            $test = $this->directory
                .ucfirst($this->translate_uri_dashes === TRUE ? str_replace('-', '_', $segments[0]) : $segments[0]);

            if ( ! file_exists(APPPATH.'controllers/'.$test.'.php')
                && $directory_override === FALSE
                && is_dir(APPPATH.'controllers/'.$this->directory.$segments[0])
            )
            {
                $this->set_directory(array_shift($segments), TRUE);
                continue;
            }

            return $segments;
        }

        // This means that all segments were actually directories
        return $segments;
    }

    // --------------------------------------------------------------------

    /**
     * Parse Routes
     *
     * Matches any routes that may exist in the config/routes.php file
     * against the URI to determine if the class/method need to be remapped.
     *
     * @return	void
     */
    protected function _parse_routes()
    {
        // Turn the segment array into a URI string
        $uri = implode('/', $this->uri->segments);

        // Loop through the route array looking for wildcards
        foreach ($this->routes as $key => $val)
        {
            // Check if route format is using HTTP verbs
            if (is_array($val))
            {
                $val = array_change_key_case($val, CASE_LOWER);
                if (array_key_exists('uses', $val))
                {
                    $val = $val['uses'];
                }
            }

            if (substr_count($val, '@') > 1)
            {
                throw new Exception('The @ symbol must be used to separate method and class like class@method, and it should only be used once. Please check your route ' . $val);
            }

            // Append all params from key to val for further processing later
            // Replace @ with / in the value
            $val = str_replace('@', '/', $val);
            $prmNames = [];

            if (preg_match_all('/\(([^:]*):(?:num|any)\)/i', $key, $matches) !== FALSE)
            {
                $count = 1;
                $prms = $matches[0];
                $prmNames = $matches[1];

                if (count($prmNames) !== count($prms))
                    throw new Exception('Not all parameters in the given route contain names. Please refer to route: ' . $key);

                if (count(array_unique($prmNames)) !== count($prmNames))
                    throw new Exception('Found duplicate names in route: ' . $key);

                if (count($prms) > 0)
                {
                    for ($i = 0; $i < count($prms); $i++)
                    {
                        if (empty(trim($prmNames[$i])))
                        {
                            throw new Exception('Route must use parameters with name. Please check route: ' . $key);
                        }

                        $key = str_replace($prmNames[$i], '', $key);
                        $val .= '/$' . $count++;
                    }
                }
            }


            // Convert wildcards to RegEx
            $key = str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $key);

            // Does the RegEx match?
            if (preg_match('#^'.$key.'$#', $uri, $matches))
            {
                // Are we using callbacks to process back-references?
                if ( ! is_string($val) && is_callable($val))
                {
                    // Remove the original string from the matches array.
                    array_shift($matches);

                    // Execute the callback using the values in matches as its parameters.
                    $val = call_user_func_array($val, $matches);
                }
                // Are we using the default routing method for back-references?
                elseif (strpos($val, '$') !== FALSE && strpos($key, '(') !== FALSE)
                {
                    $val = preg_replace('#^'.$key.'$#', $val, $uri);
                }

                $this->named_params = $prmNames;
                $this->_set_request(explode('/', $val));
                return;
            }
        }

        // If we got this far it means we didn't encounter a
        // matching route so we'll set the site default route
        $this->_set_request(array_values($this->uri->segments));
    }

    // --------------------------------------------------------------------

    /**
     * Set class name
     *
     * @param	string	$class	Class name
     * @return	void
     */
    public function set_class($class)
    {
        $this->class = str_replace(array('/', '.'), '', $class);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the current class
     *
     * @deprecated	3.0.0	Read the 'class' property instead
     * @return	string
     */
    public function fetch_class()
    {
        return $this->class;
    }

    // --------------------------------------------------------------------

    /**
     * Set method name
     *
     * @param	string	$method	Method name
     * @return	void
     */
    public function set_method($method)
    {
        $this->method = $method;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the current method
     *
     * @deprecated	3.0.0	Read the 'method' property instead
     * @return	string
     */
    public function fetch_method()
    {
        return $this->method;
    }

    // --------------------------------------------------------------------

    /**
     * Set directory name
     *
     * @param	string	$dir	Directory name
     * @param	bool	$append	Whether we're appending rather than setting the full value
     * @return	void
     */
    public function set_directory($dir, $append = FALSE)
    {
        if ($append !== TRUE OR empty($this->directory))
        {
            $this->directory = str_replace('.', '', trim($dir, '/')).'/';
        }
        else
        {
            $this->directory .= str_replace('.', '', trim($dir, '/')).'/';
        }
    }

    // --------------------------------------------------------------------

    /**
     * Fetch directory
     *
     * Feches the sub-directory (if any) that contains the requested
     * controller class.
     *
     * @deprecated	3.0.0	Read the 'directory' property instead
     * @return	string
     */
    public function fetch_directory()
    {
        return $this->directory;
    }

}