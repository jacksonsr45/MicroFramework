<?php

namespace MicroFramework\Route;

use InvalidArgumentException;

class FrameworkRoutes
{
    protected $callback;
    protected $path;
    protected $method;
    protected $count_match;
    protected $name;


    public function __construct($callback, $path = null, $method = null, $count_match = true, $name = null)
    {
        // Initialize some properties (use our setters so we can validate param types)
        $this->setCallback($callback);
        $this->setPath($path);
        $this->setMethod($method);
        $this->setCountMatch($count_match);
        $this->setName($name);
    }

    public function getCallback()
    {
        return $this->callback;
    }

    public function setCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Expected a callable. Got an uncallable '. gettype($callback));
        }

        $this->callback = $callback;

        return $this;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setPath($path)
    {
        $this->path = (string) $path;

        return $this;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setMethod($method)
    {
        // Allow null, otherwise expect an array or a string
        if (null !== $method && !is_array($method) && !is_string($method)) {
            throw new InvalidArgumentException('Expected an array or string. Got a '. gettype($method));
        }

        $this->method = $method;

        return $this;
    }

    public function getCountMatch()
    {
        return $this->count_match;
    }

    public function setCountMatch($count_match)
    {
        $this->count_match = (boolean) $count_match;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        if (null !== $name) {
            $this->name = (string) $name;
        } else {
            $this->name = $name;
        }

        return $this;
    }

    public function __invoke($args = null)
    {
        $args = func_get_args();

        return call_user_func_array(
            $this->callback,
            $args
        );
    }
}
