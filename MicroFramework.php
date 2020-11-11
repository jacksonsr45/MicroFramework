<?php

namespace MicroFramework;

use Exception;
use MicroFramework\DataCollection\RouteCollection;
use MicroFramework\Exceptions\DispatchHaltedException;
use MicroFramework\Exceptions\HttpException;
use MicroFramework\Exceptions\HttpExceptionInterface;
use MicroFramework\Exceptions\LockedResponseException;
use MicroFramework\Exceptions\RegularExpressionCompilationException;
use MicroFramework\Exceptions\RoutePathCompilationException;
use MicroFramework\Exceptions\UnhandledException;
use MicroFramework\Response\Response;
use MicroFramework\Response\AbstractResponse;
use MicroFramework\Route\FrameworkRoutes;
use MicroFramework\Factory\AbstractRouteFactory;
use MicroFramework\Factory\RouteFactory;
use MicroFramework\Request\Request;
use MicroFramework\Provider\ServiceProvider;
use OutOfBoundsException;
use SplQueue;
use SplStack;
use Throwable;

class MicroFramework
{
    const ROUTE_COMPILE_REGEX = '`(\\\?(?:/|\.|))(?:\[([^:\]]*)(?::([^:\]]*))?\])(\?|)`';
    const ROUTE_ESCAPE_REGEX = '`(?<=^|\])[^\]\[\?]+?(?=\[|$)`';
    const DISPATCH_NO_CAPTURE = 0;
    const DISPATCH_CAPTURE_AND_RETURN = 1;
    const DISPATCH_CAPTURE_AND_REPLACE = 2;
    const DISPATCH_CAPTURE_AND_PREPEND = 3;
    const DISPATCH_CAPTURE_AND_APPEND = 4;
    protected $match_types = array(
        'i'  => '[0-9]++',
        'a'  => '[0-9A-Za-z]++',
        'h'  => '[0-9A-Fa-f]++',
        's'  => '[0-9A-Za-z-_]++',
        '*'  => '.+?',
        '**' => '.++',
        ''   => '[^/]+?'
    );
    protected $routes;
    protected $route_factory;
    protected $error_callbacks;
    protected $http_error_callbacks;
    protected $after_filter_callbacks;
    private $output_buffer_level;
    protected $request;
    protected $response;
    protected $service;
    protected $app;


    public function __construct(
        ServiceProvider $service = null,
        $app = null,
        RouteCollection $routes = null,
        AbstractRouteFactory $route_factory = null
    ) {
        // Instanciate and fall back to defaults
        $this->service       = $service       ?: new ServiceProvider();
        $this->app           = $app           ?: new App();
        $this->routes        = $routes        ?: new RouteCollection();
        $this->route_factory = $route_factory ?: new RouteFactory();

        $this->error_callbacks = new SplStack();
        $this->http_error_callbacks = new SplStack();
        $this->after_filter_callbacks = new SplQueue();
    }

    public function routes()
    {
        return $this->routes;
    }

    public function request()
    {
        return $this->request;
    }

    public function response()
    {
        return $this->response;
    }

    public function service()
    {
        return $this->service;
    }

    public function app()
    {
        return $this->app;
    }

    protected function parseLooseArgumentOrder(array $args)
    {
        // Get the arguments in a very loose format
        $callback = array_pop($args);
        $path = array_pop($args);
        $method = array_pop($args);

        // Return a named parameter array
        return array(
            'method' => $method,
            'path' => $path,
            'callback' => $callback,
        );
    }

    public function respond($method, $path = '*', $callback = null)
    {
        // Get the arguments in a very loose format
        extract(
            $this->parseLooseArgumentOrder(func_get_args()),
            EXTR_OVERWRITE
        );

        $route = $this->route_factory->build($callback, $path, $method);

        $this->routes->add($route);

        return $route;
    }

    public function with($namespace, $routes)
    {
        $previous = $this->route_factory->getNamespace();

        $this->route_factory->appendNamespace($namespace);

        if (is_callable($routes)) {
            if (is_string($routes)) {
                $routes($this);
            } else {
                call_user_func($routes, $this);
            }
        } else {
            require $routes;
        }

        $this->route_factory->setNamespace($previous);
    }

    public function dispatch(
        Request $request = null,
        AbstractResponse $response = null,
        $send_response = true,
        $capture = self::DISPATCH_NO_CAPTURE
    ) {
        // Set/Initialize our objects to be sent in each callback
        $this->request = $request ?: Request::createFromGlobals();
        $this->response = $response ?: new Response();

        // Bind our objects to our service
        $this->service->bind($this->request, $this->response);

        // Prepare any named routes
        $this->routes->prepareNamed();


        // Grab some data from the request
        $uri = $this->request->pathname();
        $req_method = $this->request->method();

        // Set up some variables for matching
        $skip_num = 0;
        $matched = $this->routes->cloneEmpty(); // Get a clone of the routes collection, as it may have been injected
        $methods_matched = array();
        $params = array();
        $apc = function_exists('apc_fetch');

        // Start output buffering
        ob_start();
        $this->output_buffer_level = ob_get_level();

        try {
            foreach ($this->routes as $route) {
                // Are we skipping any matches?
                if ($skip_num > 0) {
                    $skip_num--;
                    continue;
                }

                // Grab the properties of the route handler
                $method = $route->getMethod();
                $path = $route->getPath();
                $count_match = $route->getCountMatch();

                // Keep track of whether this specific request method was matched
                $method_match = null;

                // Was a method specified? If so, check it against the current request method
                if (is_array($method)) {
                    foreach ($method as $test) {
                        if (strcasecmp($req_method, $test) === 0) {
                            $method_match = true;
                        } elseif (strcasecmp($req_method, 'HEAD') === 0
                              && (strcasecmp($test, 'HEAD') === 0 || strcasecmp($test, 'GET') === 0)) {

                            // Test for HEAD request (like GET)
                            $method_match = true;
                        }
                    }

                    if (null === $method_match) {
                        $method_match = false;
                    }
                } elseif (null !== $method && strcasecmp($req_method, $method) !== 0) {
                    $method_match = false;

                    // Test for HEAD request (like GET)
                    if (strcasecmp($req_method, 'HEAD') === 0
                        && (strcasecmp($method, 'HEAD') === 0 || strcasecmp($method, 'GET') === 0 )) {

                        $method_match = true;
                    }
                } elseif (null !== $method && strcasecmp($req_method, $method) === 0) {
                    $method_match = true;
                }

                // If the method was matched or if it wasn't even passed (in the route callback)
                $possible_match = (null === $method_match) || $method_match;

                // ! is used to negate a match
                if (isset($path[0]) && $path[0] === '!') {
                    $negate = true;
                    $i = 1;
                } else {
                    $negate = false;
                    $i = 0;
                }

                // Check for a wildcard (match all)
                if ($path === '*') {
                    $match = true;

                } elseif (($path === '404' && $matched->isEmpty() && count($methods_matched) <= 0)
                       || ($path === '405' && $matched->isEmpty() && count($methods_matched) > 0)) {

                    // Warn user of deprecation
                    trigger_error(
                        'Use of 404/405 "routes" is deprecated. Use $klein->onHttpError() instead.',
                        E_USER_DEPRECATED
                    );
                    // TODO: Possibly remove in future, here for backwards compatibility
                    $this->onHttpError($route);

                    continue;

                } elseif (isset($path[$i]) && $path[$i] === '@') {
                    // @ is used to specify custom regex

                    $match = preg_match('`' . substr($path, $i + 1) . '`', $uri, $params);

                } else {
                    // Compiling and matching regular expressions is relatively
                    // expensive, so try and match by a substring first

                    $expression = null;
                    $regex = false;
                    $j = 0;
                    $n = isset($path[$i]) ? $path[$i] : null;

                    // Find the longest non-regex substring and match it against the URI
                    while (true) {
                        if (!isset($path[$i])) {
                            break;
                        } elseif (false === $regex) {
                            $c = $n;
                            $regex = $c === '[' || $c === '(' || $c === '.';
                            if (false === $regex && false !== isset($path[$i+1])) {
                                $n = $path[$i + 1];
                                $regex = $n === '?' || $n === '+' || $n === '*' || $n === '{';
                            }
                            if (false === $regex && $c !== '/' && (!isset($uri[$j]) || $c !== $uri[$j])) {
                                continue 2;
                            }
                            $j++;
                        }
                        $expression .= $path[$i++];
                    }

                    try {
                        // Check if there's a cached regex string
                        if (false !== $apc) {
                            $regex = apc_fetch("route:$expression");
                            if (false === $regex) {
                                $regex = $this->compileRoute($expression);
                                apc_store("route:$expression", $regex);
                            }
                        } else {
                            $regex = $this->compileRoute($expression);
                        }
                    } catch (RegularExpressionCompilationException $e) {
                        throw RoutePathCompilationException::createFromRoute($route, $e);
                    }

                    $match = preg_match($regex, $uri, $params);
                }

                if (isset($match) && $match ^ $negate) {
                    if ($possible_match) {
                        if (!empty($params)) {
                            
                            $params = array_map('rawurldecode', $params);

                            $this->request->paramsNamed()->merge($params);
                        }

                        // Handle our response callback
                        try {
                            $this->handleRouteCallback($route, $matched, $methods_matched);

                        } catch (DispatchHaltedException $e) {
                            switch ($e->getCode()) {
                                case DispatchHaltedException::SKIP_THIS:
                                    continue 2;
                                    break;
                                case DispatchHaltedException::SKIP_NEXT:
                                    $skip_num = $e->getNumberOfSkips();
                                    break;
                                case DispatchHaltedException::SKIP_REMAINING:
                                    break 2;
                                default:
                                    throw $e;
                            }
                        }

                        if ($path !== '*') {
                            $count_match && $matched->add($route);
                        }
                    }

                    // Don't bother counting this as a method match if the route isn't supposed to match anyway
                    if ($count_match) {
                        // Keep track of possibly matched methods
                        $methods_matched = array_merge($methods_matched, (array) $method);
                        $methods_matched = array_filter($methods_matched);
                        $methods_matched = array_unique($methods_matched);
                    }
                }
            }

            // Handle our 404/405 conditions
            if ($matched->isEmpty() && count($methods_matched) > 0) {
                // Add our methods to our allow header
                $this->response->header('Allow', implode(', ', $methods_matched));

                if (strcasecmp($req_method, 'OPTIONS') !== 0) {
                    throw HttpException::createFromCode(405);
                }
            } elseif ($matched->isEmpty()) {
                throw HttpException::createFromCode(404);
            }

        } catch (HttpExceptionInterface $e) {
            // Grab our original response lock state
            $locked = $this->response->isLocked();

            // Call our http error handlers
            $this->httpError($e, $matched, $methods_matched);

            // Make sure we return our response to its original lock state
            if (!$locked) {
                $this->response->unlock();
            }

        } catch (Throwable $e) { // PHP 7 compatibility
            $this->error($e);
        } catch (Exception $e) { // TODO: Remove this catch block once PHP 5.x support is no longer necessary.
            $this->error($e);
        }

        try {
            if ($this->response->chunked) {
                $this->response->chunk();

            } else {
                // Output capturing behavior
                switch($capture) {
                    case self::DISPATCH_CAPTURE_AND_RETURN:
                        $buffed_content = null;
                        while (ob_get_level() >= $this->output_buffer_level) {
                            $buffed_content = ob_get_clean();
                        }
                        return $buffed_content;
                        break;
                    case self::DISPATCH_CAPTURE_AND_REPLACE:
                        while (ob_get_level() >= $this->output_buffer_level) {
                            $this->response->body(ob_get_clean());
                        }
                        break;
                    case self::DISPATCH_CAPTURE_AND_PREPEND:
                        while (ob_get_level() >= $this->output_buffer_level) {
                            $this->response->prepend(ob_get_clean());
                        }
                        break;
                    case self::DISPATCH_CAPTURE_AND_APPEND:
                        while (ob_get_level() >= $this->output_buffer_level) {
                            $this->response->append(ob_get_clean());
                        }
                        break;
                    default:
                        // If not a handled capture strategy, default to no capture
                        $capture = self::DISPATCH_NO_CAPTURE;
                }
            }

            // Test for HEAD request (like GET)
            if (strcasecmp($req_method, 'HEAD') === 0) {
                // HEAD requests shouldn't return a body
                $this->response->body('');

                while (ob_get_level() >= $this->output_buffer_level) {
                    ob_end_clean();
                }
            } elseif (self::DISPATCH_NO_CAPTURE === $capture) {
                while (ob_get_level() >= $this->output_buffer_level) {
                    ob_end_flush();
                }
            }
        } catch (LockedResponseException $e) {
            // Do nothing, since this is an automated behavior
        }

        // Run our after dispatch callbacks
        $this->callAfterDispatchCallbacks();

        if ($send_response && !$this->response->isSent()) {
            $this->response->send();
        }
    }

    protected function compileRoute($route)
    {
        // First escape all of the non-named param (non [block]s) for regex-chars
        $route = preg_replace_callback(
            static::ROUTE_ESCAPE_REGEX,
            function ($match) {
                return preg_quote($match[0]);
            },
            $route
        );

        // Get a local reference of the match types to pass into our closure
        $match_types = $this->match_types;

        // Now let's actually compile the path
        $route = preg_replace_callback(
            static::ROUTE_COMPILE_REGEX,
            function ($match) use ($match_types) {
                list(, $pre, $type, $param, $optional) = $match;

                if (isset($match_types[$type])) {
                    $type = $match_types[$type];
                }

                // Older versions of PCRE require the 'P' in (?P<named>)
                $pattern = '(?:'
                         . ($pre !== '' ? $pre : null)
                         . '('
                         . ($param !== '' ? "?P<$param>" : null)
                         . $type
                         . '))'
                         . ($optional !== '' ? '?' : null);

                return $pattern;
            },
            $route
        );

        $regex = "`^$route$`";

        // Check if our regular expression is valid
        $this->validateRegularExpression($regex);

        return $regex;
    }

    private function validateRegularExpression($regex)
    {
        $error_string = null;

        // Set an error handler temporarily
        set_error_handler(
            function ($errno, $errstr) use (&$error_string) {
                $error_string = $errstr;
            },
            E_NOTICE | E_WARNING
        );

        if (false === preg_match($regex, null) || !empty($error_string)) {
            // Remove our temporary error handler
            restore_error_handler();

            throw new RegularExpressionCompilationException(
                $error_string,
                preg_last_error()
            );
        }

        // Remove our temporary error handler
        restore_error_handler();

        return true;
    }

    public function getPathFor($route_name, array $params = null, $flatten_regex = true)
    {
        // First, grab the route
        $route = $this->routes->get($route_name);

        // Make sure we are getting a valid route
        if (null === $route) {
            throw new OutOfBoundsException('No such route with name: '. $route_name);
        }

        $path = $route->getPath();

        // Use our compilation regex to reverse the path's compilation from its definition
        $reversed_path = preg_replace_callback(
            static::ROUTE_COMPILE_REGEX,
            function ($match) use ($params) {
                list($block, $pre, , $param, $optional) = $match;

                if (isset($params[$param])) {
                    return $pre. $params[$param];
                } elseif ($optional) {
                    return '';
                }

                return $block;
            },
            $path
        );

        // If the path and reversed_path are the same, the regex must have not matched/replaced
        if ($path === $reversed_path && $flatten_regex && strpos($path, '@') === 0) {
            // If the path is a custom regular expression and we're "flattening", just return a slash
            $path = '/';
        } else {
            $path = $reversed_path;
        }

        return $path;
    }

    protected function handleRouteCallback(FrameworkRoutes $route, RouteCollection $matched, array $methods_matched)
    {
        // Handle the callback
        $returned = call_user_func(
            $route->getCallback(), // Instead of relying on the slower "invoke" magic
            $this->request,
            $this->response,
            $this->service,
            $this->app,
            $this, // Pass the Klein instance
            $matched,
            $methods_matched
        );

        if ($returned instanceof AbstractResponse) {
            $this->response = $returned;
        } else {
            // Otherwise, attempt to append the returned data
            try {
                $this->response->append($returned);
            } catch (LockedResponseException $e) {
                // Do nothing, since this is an automated behavior
            }
        }
    }

    public function onError($callback)
    {
        $this->error_callbacks->push($callback);
    }

    protected function error($err)
    {
        $type = get_class($err);
        $msg = $err->getMessage();

        try {
            if (!$this->error_callbacks->isEmpty()) {
                foreach ($this->error_callbacks as $callback) {
                    if (is_callable($callback)) {
                        if (is_string($callback)) {
                            $callback($this, $msg, $type, $err);

                            return;
                        } else {
                            call_user_func($callback, $this, $msg, $type, $err);

                            return;
                        }
                    } else {
                        if (null !== $this->service && null !== $this->response) {
                            $this->service->flash($err);
                            $this->response->redirect($callback);
                        }
                    }
                }
            } else {
                $this->response->code(500);

                while (ob_get_level() >= $this->output_buffer_level) {
                    ob_end_clean();
                }

                throw new UnhandledException($msg, $err->getCode(), $err);
            }
        } catch (Throwable $e) { // PHP 7 compatibility
            // Make sure to clean the output buffer before bailing
            while (ob_get_level() >= $this->output_buffer_level) {
                ob_end_clean();
            }

            throw $e;
        } catch (Exception $e) { // TODO: Remove this catch block once PHP 5.x support is no longer necessary.
            // Make sure to clean the output buffer before bailing
            while (ob_get_level() >= $this->output_buffer_level) {
                ob_end_clean();
            }

            throw $e;
        }

        // Lock our response, since we probably don't want
        // anything else messing with our error code/body
        $this->response->lock();
    }

    public function onHttpError($callback)
    {
        $this->http_error_callbacks->push($callback);
    }

    protected function httpError(HttpExceptionInterface $http_exception, RouteCollection $matched, $methods_matched)
    {
        if (!$this->response->isLocked()) {
            $this->response->code($http_exception->getCode());
        }

        if (!$this->http_error_callbacks->isEmpty()) {
            foreach ($this->http_error_callbacks as $callback) {
                if ($callback instanceof FrameworkRoutes) {
                    $this->handleRouteCallback($callback, $matched, $methods_matched);
                } elseif (is_callable($callback)) {
                    if (is_string($callback)) {
                        $callback(
                            $http_exception->getCode(),
                            $this,
                            $matched,
                            $methods_matched,
                            $http_exception
                        );
                    } else {
                        call_user_func(
                            $callback,
                            $http_exception->getCode(),
                            $this,
                            $matched,
                            $methods_matched,
                            $http_exception
                        );
                    }
                }
            }
        }

        // Lock our response, since we probably don't want
        // anything else messing with our error code/body
        $this->response->lock();
    }

    public function afterDispatch($callback)
    {
        $this->after_filter_callbacks->enqueue($callback);
    }

    protected function callAfterDispatchCallbacks()
    {
        try {
            foreach ($this->after_filter_callbacks as $callback) {
                if (is_callable($callback)) {
                    if (is_string($callback)) {
                        $callback($this);

                    } else {
                        call_user_func($callback, $this);

                    }
                }
            }
        } catch (Throwable $e) { // PHP 7 compatibility
            $this->error($e);
        } catch (Exception $e) { // TODO: Remove this catch block once PHP 5.x support is no longer necessary.
            $this->error($e);
        }
    }


    public function skipThis()
    {
        throw new DispatchHaltedException(null, DispatchHaltedException::SKIP_THIS);
    }

    public function skipNext($num = 1)
    {
        $skip = new DispatchHaltedException(null, DispatchHaltedException::SKIP_NEXT);
        $skip->setNumberOfSkips($num);

        throw $skip;
    }

    public function skipRemaining()
    {
        throw new DispatchHaltedException(null, DispatchHaltedException::SKIP_REMAINING);
    }

    public function abort($code = null)
    {
        if (null !== $code) {
            throw HttpException::createFromCode($code);
        }

        throw new DispatchHaltedException();
    }

    public function options($path = '*', $callback = null)
    {
        // Options the arguments in a very loose format
        extract(
            $this->parseLooseArgumentOrder(func_get_args()),
            EXTR_OVERWRITE
        );

        return $this->respond('OPTIONS', $path, $callback);
    }

    public function head($path = '*', $callback = null)
    {
        // Get the arguments in a very loose format
        extract(
            $this->parseLooseArgumentOrder(func_get_args()),
            EXTR_OVERWRITE
        );

        return $this->respond('HEAD', $path, $callback);
    }

    public function get($path = '*', $callback = null)
    {
        // Get the arguments in a very loose format
        extract(
            $this->parseLooseArgumentOrder(func_get_args()),
            EXTR_OVERWRITE
        );

        return $this->respond('GET', $path, $callback);
    }

    public function post($path = '*', $callback = null)
    {
        // Get the arguments in a very loose format
        extract(
            $this->parseLooseArgumentOrder(func_get_args()),
            EXTR_OVERWRITE
        );

        return $this->respond('POST', $path, $callback);
    }

    public function put($path = '*', $callback = null)
    {
        // Get the arguments in a very loose format
        extract(
            $this->parseLooseArgumentOrder(func_get_args()),
            EXTR_OVERWRITE
        );

        return $this->respond('PUT', $path, $callback);
    }

    public function delete($path = '*', $callback = null)
    {
        // Get the arguments in a very loose format
        extract(
            $this->parseLooseArgumentOrder(func_get_args()),
            EXTR_OVERWRITE
        );

        return $this->respond('DELETE', $path, $callback);
    }

    
    public function patch($path = '*', $callback = null)
    {
        // Get the arguments in a very loose format
        extract(
            $this->parseLooseArgumentOrder(func_get_args()),
            EXTR_OVERWRITE
        );

        return $this->respond('PATCH', $path, $callback);
    }
}
