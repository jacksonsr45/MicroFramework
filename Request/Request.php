<?php

namespace MicroFramework\Request;

use MicroFramework\DataCollection\DataCollection;
use MicroFramework\DataCollection\HeaderDataCollection;
use MicroFramework\DataCollection\ServerDataCollection;

class Request
{
    protected $id;
    protected $params_get;
    protected $params_post;
    protected $params_named;
    protected $cookies;
    protected $server;
    protected $headers;
    protected $files;
    protected $body;


    public function __construct(
        array $params_get = array(),
        array $params_post = array(),
        array $cookies = array(),
        array $server = array(),
        array $files = array(),
        $body = null
    ) {
        // Assignment city...
        $this->params_get   = new DataCollection($params_get);
        $this->params_post  = new DataCollection($params_post);
        $this->cookies      = new DataCollection($cookies);
        $this->server       = new ServerDataCollection($server);
        $this->headers      = new HeaderDataCollection($this->server->getHeaders());
        $this->files        = new DataCollection($files);
        $this->body         = $body ? (string) $body : null;

        // Non-injected assignments
        $this->params_named = new DataCollection();
    }

    public static function createFromGlobals()
    {
        // Create and return a new instance of this
        return new static(
            $_GET,
            $_POST,
            $_COOKIE,
            $_SERVER,
            $_FILES,
            null // Let our content getter take care of the "body"
        );
    }

    public function id($hash = true)
    {
        if (null === $this->id) {
            $this->id = uniqid();

            if ($hash) {
                $this->id = sha1($this->id);
            }
        }

        return $this->id;
    }

    public function paramsGet()
    {
        return $this->params_get;
    }

    public function paramsPost()
    {
        return $this->params_post;
    }

    public function paramsNamed()
    {
        return $this->params_named;
    }

    public function cookies()
    {
        return $this->cookies;
    }

    public function server()
    {
        return $this->server;
    }

    public function headers()
    {
        return $this->headers;
    }

    public function files()
    {
        return $this->files;
    }

    public function body()
    {
        // Only get it once
        if (null === $this->body) {
            $this->body = @file_get_contents('php://input');
        }

        return $this->body;
    }

    public function params($mask = null, $fill_with_nulls = true)
    {
        /*
         * Make sure that each key in the mask has at least a
         * null value, since the user will expect the key to exist
         */
        if (null !== $mask && $fill_with_nulls) {
            $attributes = array_fill_keys($mask, null);
        } else {
            $attributes = array();
        }

        // Merge our params in the get, post, cookies, named order
        return array_merge(
            $attributes,
            $this->params_get->all($mask, false),
            $this->params_post->all($mask, false),
            $this->cookies->all($mask, false),
            $this->params_named->all($mask, false) // Add our named params last
        );
    }

    public function param($key, $default = null)
    {
        // Get all of our request params
        $params = $this->params();

        return isset($params[$key]) ? $params[$key] : $default;
    }

    public function __isset($param)
    {
        // Get all of our request params
        $params = $this->params();

        return isset($params[$param]);
    }

    public function __get($param)
    {
        return $this->param($param);
    }

    public function __set($param, $value)
    {
        $this->params_named->set($param, $value);
    }

    public function __unset($param)
    {
        $this->params_named->remove($param);
    }

    public function isSecure()
    {
        return ($this->server->get('HTTPS') == true);
    }

    public function ip()
    {
        return $this->server->get('REMOTE_ADDR');
    }

    public function userAgent()
    {
        return $this->headers->get('USER_AGENT');
    }

    public function uri()
    {
        return $this->server->get('REQUEST_URI', '/');
    }

    public function pathname()
    {
        $uri = $this->uri();

        // Strip the query string from the URI
        $uri = strstr($uri, '?', true) ?: $uri;

        return $uri;
    }

    public function method($is = null, $allow_override = true)
    {
        $method = $this->server->get('REQUEST_METHOD', 'GET');

        // Override
        if ($allow_override && $method === 'POST') {
            // For legacy servers, override the HTTP method with the X-HTTP-Method-Override header or _method parameter
            if ($this->server->exists('X_HTTP_METHOD_OVERRIDE')) {
                $method = $this->server->get('X_HTTP_METHOD_OVERRIDE', $method);
            } else {
                $method = $this->param('_method', $method);
            }

            $method = strtoupper($method);
        }

        // We're doing a check
        if (null !== $is) {
            return strcasecmp($method, $is) === 0;
        }

        return $method;
    }

    public function query($key, $value = null)
    {
        $query = array();

        parse_str(
            $this->server()->get('QUERY_STRING'),
            $query
        );

        if (is_array($key)) {
            $query = array_merge($query, $key);
        } else {
            $query[$key] = $value;
        }

        $request_uri = $this->uri();

        if (strpos($request_uri, '?') !== false) {
            $request_uri = strstr($request_uri, '?', true);
        }

        return $request_uri . (!empty($query) ? '?' . http_build_query($query) : null);
    }
}
