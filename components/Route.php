<?php

namespace cyneek\yii2\routes\components;

// TODO: add filters so that you can pass data manually as in laravel with a:

use Closure;
use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;

class Route
{
    /**
     * @var _Route_object[]
     */
    private static $routes = [];

    /**
     * @var array
     */
    private static $filters = [];

    /**
     * @var string[]
     */
    private static $prefix = [];

    /**
     * @var array
     */
    private static $group_options = [];

    /**
     * @var string[]
     */
    private static $pattern = [];

    /**
     * @var array
     */
    private static $when_filters = [];

    /**
     * @var string
     */
    private static $alias_prefix = '@_route_';

    /**
     * @return mixed
     */
    public static function map()
    {
        $return_data = [];


        // add special patterns: :any and :num
        self::pattern('(:any)', '(.+)');
        self::pattern('(:num)', '\d+');

        /**
         * @var _Route_object $route
         */
        foreach (self::$routes as $route) {
            $routeData = $route->make();
            $filters = [];
            // adds before and after filter routes
            // only for closure filters
            foreach (['before', 'after'] as $filterType) {
                foreach ($routeData['filters'][$filterType] as $filterName) {
                    if (array_key_exists($filterName, self::$filters)) {
                        $new_filter = self::$filters[$filterName];

                        $new_filter['type'] = $filterType;
                        $new_filter['only'][] = $routeData['to'];

                        $filters[] = $new_filter;
                    }
                }
            }

            // adds the rest of the filters
            foreach ($routeData['filters']['filter'] as $filterName) {
                if (array_key_exists($filterName, self::$filters)) {
                    $new_filter = self::$filters[$filterName];

                    $new_filter['only'][] = $routeData['to'];

                    $filters[] = $new_filter;
                }
            }

            $return_data[$routeData['from']] = [
                'to' => $routeData['to'],
                'filters' => $filters
            ];

        }

        //$return_data['filters']	= self::$filters;

        return $return_data;
    }

    /**
     * Adds a pattern like {id} with it's regex equivalent
     *
     * @param mixed $definition
     * @param string $regex
     */
    public static function pattern($definition, $regex = NULL)
    {
        if ($regex !== null && is_string($definition)) {
            if ($regex === '(:any)') {
                $regex = '(.+)';
            } elseif ($regex === '(:num)') {
                $regex = '\d+';
            }

            self::$pattern[$definition] = $regex;
        } elseif ($regex === null && is_array($definition)) {
            foreach ($definition as $key => $reg) {
                self::pattern($key, $reg);
            }
        }
    }

    /**
     * @param string $from
     * @param string $to
     * @param array $options
     * @param mixed $nested
     * @return _Route_object_facade
     * @throws InvalidConfigException
     */
    public static function any($from, $to, $options = [], $nested = false)
    {
        return self::createRoute('', $from, $to, $options, $nested);
    }

    /**
     * Does the heavy lifting creating route objects
     *
     * @param string $type
     * @param string $from
     * @param string $to
     * @param array $options
     * @param mixed $nested
     * @return _Route_object_facade
     * @throws InvalidConfigException
     */
    public static function createRoute($type, $from, $to, $options = [], $nested = false)
    {
        $group_options = self::getGroupOptions();

        if (!empty($group_options)) {
            $options = array_merge($options, $group_options);
        }

        $route_object = new _Route_object($type, self::getPrefix($from), $to, $options, $nested);

        self::$routes[] = $route_object;

        $route_object->launchOptionalRoutes();

        // if route has a nested parameter, we will call group for this route
        if ($nested != false) {
            $options['prefix'] = $from;
            self::group($nested, $options);
        }

        return new _Route_object_facade($route_object);
    }

    /**
     * Returns the last options from the active prefix, in case there is none, will return an empty array
     * @return array
     */
    private static function getGroupOptions()
    {
        if (!empty(self::$group_options)) {
            return end(self::$group_options);
        }

        return [];
    }

    /**
     * Returns the last active prefix or an empty string in case there is none
     * @param string $from
     * @return string
     */
    private static function getPrefix($from = '')
    {
        if (!empty(self::$prefix)) {
            $prefix_string = implode('/', self::$prefix);

            if (!$from === '') {

                if (substr($from, 0) === '/') {
                    $from = ltrim($from, '/');
                }

                $prefix_string .= $from;
            }

            return $prefix_string;

        }

        return $from;
    }

    /**
     * @param array $options
     * @param callable $routes
     */
    public static function group($routes, $options = [])
    {
        if (array_key_exists('prefix', $options)) {
            self::addPrefix($options['prefix']);
        }

        self::addGroupOptions($options);

        if (self::is_closure($routes)) {
            $routes();
        }

        self::deleteGroupOptions();

        if (array_key_exists('prefix', $options)) {
            self::deletePrefix();
        }
    }

    /**
     * Adds one level of prefix routing
     *
     * @param string $prefix
     */
    private static function addPrefix($prefix)
    {
        if (substr($prefix, -1) !== '/') {
            $prefix .= '/';
        }

        self::$prefix[] = $prefix;
    }

    /**
     * Adds a list of options from the new prefix
     * @param array $options
     */
    private static function addGroupOptions($options)
    {
        self::$group_options[] = $options;
    }

    private static function is_closure($routes)
    {
        return (is_object($routes) && ($routes instanceof Closure));
    }

    /**
     * Deletes one level of prefix options
     */
    private static function deleteGroupOptions()
    {
        array_pop(self::$group_options);
    }

    /**
     * Deletes one level of prefix routing
     */
    private static function deletePrefix()
    {
        array_pop(self::$prefix);
    }

    /**
     * @param string $from
     * @param string $to
     * @param array $options
     * @param mixed $nested
     * @return _Route_object_facade
     */
    public static function get($from, $to, $options = [], $nested = false)
    {
        return self::createRoute('GET', $from, $to, $options, $nested);
    }

    /**
     * @param string $from
     * @param string $to
     * @param array $options
     * @param mixed $nested
     * @return _Route_object_facade
     */
    public static function post($from, $to, $options = [], $nested = false)
    {
        return self::createRoute('POST', $from, $to, $options, $nested);
    }

    /**
     * @param string $from
     * @param string $to
     * @param array $options
     * @param mixed $nested
     * @return _Route_object_facade
     */
    public static function put($from, $to, $options = [], $nested = false)
    {
        return self::createRoute('PUT', $from, $to, $options, $nested);
    }

    /**
     * @param string $from
     * @param string $to
     * @param array $options
     * @param mixed $nested
     * @return _Route_object_facade
     */
    public static function delete($from, $to, $options = [], $nested = false)
    {
        return self::createRoute('DELETE', $from, $to, $options, $nested);
    }

    /**
     * @param string $from
     * @param string $to
     * @param array $options
     * @param mixed $nested
     * @return _Route_object_facade
     */
    public static function head($from, $to, $options = [], $nested = false)
    {
        return self::createRoute('HEAD', $from, $to, $options, $nested);
    }

    /**
     * @param string $from
     * @param string $to
     * @param array $options
     * @param mixed $nested
     * @return _Route_object_facade
     */
    public static function patch($from, $to, $options = [], $nested = false)
    {
        return self::createRoute('PATCH', $from, $to, $options, $nested);
    }

    /**
     * Let's the user define manually the http verbs that will have this route
     * @param array|string $http_verb
     * @param string $from
     * @param string $to
     * @param array $options
     * @param mixed $nested
     * @return _Route_object_facade
     */
    public static function match($http_verb, $from, $to, $options = [], $nested = false)
    {
        if (is_array($http_verb)) {
            $http_verb = implode(',', $http_verb);
        }

        $http_verb = strtoupper($http_verb);

        return self::createRoute($http_verb, $from, $to, $options, $nested);
    }

    /**
     * Syntactic sugar to get the named uri parameters
     * @param string $uri_name
     * @return array|mixed
     */
    public static function input($uri_name)
    {
        return Yii::$app->request->get($uri_name);
    }

    /**
     * Adds a named route into the system
     *
     * @param string $route_alias
     * @param string $route_from
     */
    public static function set_name($route_alias, $route_from)
    {
        Yii::setAlias(self::$alias_prefix . $route_alias, $route_from);
    }

    /**
     * @param array|string $route
     * @param string[] $parameters
     * @param boolean $scheme
     * @return string
     */
    public static function named($route, $parameters = [], $scheme = false)
    {
        $route = (array)$route;
        $route[0] = Yii::getAlias(self::$alias_prefix . $route[0]);

        foreach ($parameters as $name => $value) {
            $route[0] = preg_replace('/{' . $name . '\??}/', $value, $route[0]);
        }

        // get rid of remaining optional uri parameters
        // because we may only want to use some or none of them
        $route[0] = preg_replace('/{(.*)\?}/', '', $route[0]);


        return Url::toRoute($route, $scheme);
    }

    /**
     * Returns an existing pattern or NULL
     *
     * @param string $definition
     * @return NULL/string
     */
    public static function getPattern($definition)
    {
        if (array_key_exists($definition, self::$pattern)) {
            return self::$pattern[$definition];
        }

        return NULL;
    }

    /**
     * Adds a new filter pattern that will be added to all routes that match with its defined regex
     * @param string $regex_pattern
     * @param mixed $filter
     * @param array $http_verb
     * @throws Exception
     */
    public static function when($regex_pattern, $filter, $http_verb = ['ANY'])
    {

        if (!is_array($http_verb)) {
            throw new Exception('[Route::when] The http verb of filter patterns must be an array.');
        }

        if (!is_array($filter)) {
            $filter = ['filter' => $filter];
        }

        // $filter can only have 3 keys, 'before', 'after' and 'filter'

        $filter_allowed_keys = ['before', 'after', 'filter'];

        foreach ($filter as $key => $data) {
            if (!in_array($key, $filter_allowed_keys)) {
                throw new Exception('[Route::when] Filter must have only before, after or filter keys.');
            }
        }

        // add regex start and ending if not setted
        if (!(strpos($regex_pattern, '/') === 0 && $regex_pattern[strlen($regex_pattern) - 1] === '/')) {
            $regex_pattern = '/' . $regex_pattern . '$/';
        }

        foreach ($http_verb as $key => $verb) {
            $http_verb[$key] = strtoupper($verb);
        }

        self::$when_filters[] = [
            'regex_pattern' => $regex_pattern,
            'filter' => $filter,
            'http_verb' => $http_verb
        ];
    }

    /**
     * Returns all the pattern filters that match with its defined regex
     * @param string $from
     * @return array
     */
    public static function getWhenFilters($from)
    {
        $filters = [];

        $request = Yii::$app->getRequest();
        $request_method = $request->getMethod();

        foreach (self::$when_filters as $when) {
            if (in_array('ANY', $when['http_verb'])) {
                $valid_request_method = true;
            } elseif (in_array($request_method, $when['http_verb'])) {
                $valid_request_method = true;
            } else {
                $valid_request_method = false;
            }

            if ($valid_request_method === true && preg_match($when['regex_pattern'], $from)) {
                foreach ($when['filter'] as $type => $filter) {
                    $filters[$type][] = $filter;
                }
            }
        }

        return $filters;
    }

    /**
     * Adds a new filter into the Routing system
     * It will be set as RoutesFilter object in case $data is a closure (Laravel standard filtering) or
     * as a standard filter if $data it's an array
     *
     * @param string $filterName
     * @param array|callable|string $data
     */
    public static function filter($filterName, $data)
    {

        if (self::is_closure($data)) {
            self::$filters[$filterName] = [
                'class' => RoutesFilter::class,
                'rule' => $data
            ];
        } elseif (is_array($data)) {
            self::$filters[$filterName] = $data;
        } elseif (is_string($data)) {
            self::$filters[$filterName] =
                [
                    'class' => $data
                ];
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    public static function show_404()
    {
        throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
    }

}

class _Route_object_facade
{
    /**
     * @var _Route_object
     */
    private $route_object;


    /**
     * @param _Route_object $route_object
     */
    public function __construct($route_object)
    {
        $this->route_object = $route_object;
    }

    /**
     * Adds a named parameter that will only work locally in the route_object
     * @param string|array $parameter_name
     * @param string $parameter_expression
     */
    public function where($parameter_name, $parameter_expression = NULL)
    {
        if ($parameter_expression === null && is_array($parameter_name)) {
            foreach ($parameter_name as $key => $expression) {
                $this->where($key, $expression);
            }
        } else {
            $this->route_object->addLocalPattern($parameter_name, $parameter_expression);
        }
    }
}

class _Route_object
{

    /**
     * @var string
     */
    private $type;
    /**
     * @var string
     */
    private $from;
    /**
     * @var string
     */
    private $checked_from;
    /**
     * Tells if the from() method has been already called
     * in order to avoid calling again the from making route calls
     * @var bool
     */
    private $check_from_made = false;
    /**
     * @var array
     */
    private $local_patterns = [];
    /**
     * @var string
     */
    private $to;
    /**
     * @var array
     */
    private $options;

    /**
     * @var string[]
     */
    private $patterns = [];

    /**
     * @var array
     */
    private $optional_parameters = [];

    /**
     * @var _Route_object_facade[]
     */
    private $optional_routesList = [];

    /**
     * @var bool
     */
    private $nested;

    /**
     * @param string $type
     * @param string $from
     * @param string $to
     * @param array $options
     * @param bool $nested
     * @throws InvalidConfigException
     */
    public function __construct($type, $from, $to, $options, $nested)
    {
        $this->type = $type;
        $this->from = $from;
        $this->checked_from = $from;
        $this->to = $to;
        $this->options = $options;
        $this->nested = $nested;

        $this->makeParams();
    }


    /**
     * @throws InvalidConfigException
     */
    private function makeParams()
    {
        // check for domain options

        if (array_key_exists('domain', $this->options)) {

            $web_domain = preg_replace('/http(s?):\/\//', '', Yii::$app->urlManager->getHostInfo());
            $web_domain = explode('.', $web_domain);

            // check if there is a chance of having a subdomain
            if (count($web_domain) > 2) {
                unset($web_domain[0]);
            }

            $web_domain = implode('.', $web_domain);

            $this->options['domain'] = 'http://' . $this->options['domain'] . '.' . $web_domain;

            if (substr($this->options['domain'], -1) !== '/') {
                $this->options['domain'] .= '/';
            }


            $this->from = $this->options['domain'] . $this->from;
            $this->checked_from = $this->from;
        }

        preg_match_all('/\{(.*?)}/', $this->from, $check);
        preg_match_all('/\(:(num|any)\)/', $this->from, $check2);

        $uris = [];

        if (array_key_exists(1, $check) && !empty($check[1])) {
            foreach ($check[1] as $c) {
                if (substr($c, -1) === '?') {
                    $c = rtrim($c, '?');
                    $uris[] = $c;
                    $this->checked_from = str_replace('{' . $c . '?}', '{' . $c . '}', $this->checked_from);
                }

                $this->patterns[] = $c;
            }
        }

        if (array_key_exists(0, $check2) && !empty($check2[0])) {
            foreach ($check2[0] as $c) {
                $this->patterns[] = $c;
            }
        }

        // check optional parameters to
        if (!empty($uris)) {
            $num = count($uris);

            //The total number of possible combinations
            $total = 2 ** $num;

            //Loop through each possible combination
            for ($i = 0; $i < $total; $i++) {
                $sub_list = [];

                foreach ($uris as $j => $jValue) {
                    //Is bit $j set in $i?
                    if ((2 ** $j) & $i) {
                        $sub_list[] = $jValue;
                    }
                }

                $this->optional_parameters[] = $sub_list;
            }

            if (!empty($this->optional_parameters)) {
                array_shift($this->optional_parameters);
            }
        }
    }

    /**
     * Makes the optional additional routes. It's important to keep the
     * data the same as the parent route
     *
     * This method MUST be called BEFORE calling checkFrom()
     *
     */
    public function launchOptionalRoutes()
    {
        foreach ($this->optional_parameters as $parameters) {
            $sub_from = $this->checked_from;

            foreach ($parameters as $c) {
                $sub_from = str_replace('/{' . $c . '}', '', $sub_from);
            }

            // we get rid of the optional question mark because we will launch all optional
            // parameters from the parent route

            $new_options = $this->options;

            $not_passing_options = ['as', 'domain'];

            foreach ($not_passing_options as $not_option) {
                if (array_key_exists($not_option, $new_options)) {
                    unset($new_options[$not_option]);
                }
            }

            $this->optional_routesList[] = Route::createRoute($this->type, $sub_from, $this->to, $new_options, $this->nested);
        }

    }

    /**
     * Adds a local pattern to the route and all it's optional versions
     * @param string $pattern
     * @param mixed $pattern_expression
     */
    public function addLocalPattern($pattern, $pattern_expression)
    {
        $this->local_patterns[$pattern] = $pattern_expression;

        foreach ($this->optional_routesList as $route) {
            $route->where($pattern, $pattern_expression);
        }
    }

    /**
     * Returns the transformed data of the Route object that will be
     * processed in the Module
     *
     */
    public function make()
    {
        $from = $this->from();

        // makes the aliases
        if ($this->get_option('as') !== false) {
            Route::set_name($this->get_option('as'), $this->from);
        }

        $filters = $this->makeFilters();

        return ['from' => $from, 'to' => $this->to(), 'filters' => $filters];
    }

    /**
     * Transforms the $from object variable
     *
     * @return string
     */
    private function from()
    {
        if ($this->check_from_made === false) {
            $this->checkFrom();

            $this->check_from_made = true;
        }

        $returnString = $this->checked_from;

        if ($this->type !== '') {
            $returnString = $this->type . ' ' . $returnString;
        }

        return $returnString;
    }

    /**
     * Changes the Laravel-like uri patterns into Yii2 uri patterns in the $checked_from variable
     * that will be used to make the final from route in $this->from()
     */
    private function checkFrom()
    {
        $param_number = 1;

        foreach ($this->patterns as $c) {
            if (array_key_exists($c, $this->local_patterns)) {
                $patternRegex = $this->local_patterns[$c];
            } else {
                $patternRegex = Route::getPattern($c);
            }

            if ($patternRegex === null) {
                $patternRegex = '(.+)';
            }

            if (strpos($c, '(') === 0) {
                $this->checked_from = str_replace($c, '<var_' . $param_number++ . ':' . $patternRegex . '>', $this->checked_from);
            } else {
                $this->checked_from = str_replace('{' . $c . '}', '<' . $c . ':' . $patternRegex . '>', $this->checked_from);
            }
        }
    }

    /**
     * Returns the option passed in $option parameter in case it exists in
     * $this->options or otherwise, false
     *
     * @param string $option
     * @return bool|mixed
     */
    private function get_option($option)
    {
        if (!array_key_exists($option, $this->options)) {
            return false;
        }

        return $this->options[$option];
    }

    private function makeFilters()
    {
        $from = $this->from();

        // checks the "when" filters
        $pattern_filters = Route::getWhenFilters($from);

        // adds the filter callings into the system
        if ($this->get_option('before') !== false) {
            if (is_string($this->get_option('before'))) {
                $filter_checked = explode('|', $this->get_option('before'));
            } else {
                $filter_checked = $this->get_option('before');
            }
            $filters['before'] = $filter_checked;
        } else {
            $filters['before'] = [];
        }

        if (array_key_exists('before', $pattern_filters)) {
            $filters['before'] = array_merge($filters['before'], $pattern_filters['before']);
        }


        if ($this->get_option('after') !== false) {
            if (is_string($this->get_option('after'))) {
                $filter_checked = explode('|', $this->get_option('after'));
            } else {
                $filter_checked = $this->get_option('after');
            }
            $filters['after'] = $filter_checked;
        } else {
            $filters['after'] = [];
        }

        if (array_key_exists('after', $pattern_filters)) {
            $filters['after'] = array_merge($filters['after'], $pattern_filters['after']);
        }

        if ($this->get_option('filter') !== false) {
            if (is_string($this->get_option('filter'))) {
                $filter_checked = explode('|', $this->get_option('filter'));
            } else {
                $filter_checked = $this->get_option('filter');
            }
            $filters['filter'] = $filter_checked;
        } else {
            $filters['filter'] = [];
        }

        if (array_key_exists('filter', $pattern_filters)) {
            $filters['filter'] = array_merge($filters['filter'], $pattern_filters['filter']);
        }

        return $filters;

    }

    /**
     * Transforms the $to object variable
     *
     * @return string
     */
    private function to()
    {
        $returnString = '';

        $returnString .= $this->to;

        return $returnString;
    }

}
