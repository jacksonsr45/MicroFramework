<?php

namespace MicroFramework;

use BadMethodCallException;
use MicroFramework\Exceptions\DuplicateServiceException;
use MicroFramework\Exceptions\UnknownServiceException;


class App
{
    protected $services = array();

    public function __get($name)
    {
        if (!isset($this->services[$name])) {
            throw new UnknownServiceException('Unknown service '. $name);
        }
        $service = $this->services[$name];

        return $service();
    }

    public function __call($method, $args)
    {
        if (!isset($this->services[$method]) || !is_callable($this->services[$method])) {
            throw new BadMethodCallException('Unknown method '. $method .'()');
        }

        return call_user_func_array($this->services[$method], $args);
    }

    public function register($name, $closure)
    {
        if (isset($this->services[$name])) {
            throw new DuplicateServiceException('A service is already registered under '. $name);
        }

        $this->services[$name] = function () use ($closure) {
            static $instance;
            if (null === $instance) {
                $instance = $closure();
            }

            return $instance;
        };
    }
}
