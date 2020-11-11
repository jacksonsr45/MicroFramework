<?php

namespace MicroFramework\Validator;

use BadMethodCallException;
use MicroFramework\Exceptions\ValidationException;

class Validator
{
    public static $methods = array();
    protected $str;
    protected $err;
    protected static $default_added = false;


    public function __construct($str, $err = null)
    {
        $this->str = $str;
        $this->err = $err;

        if (!static::$default_added) {
            static::addDefault();
        }
    }

    public static function addDefault()
    {
        static::$methods['null'] = function ($str) {
            return $str === null || $str === '';
        };
        static::$methods['len'] = function ($str, $min, $max = null) {
            $len = strlen($str);
            return null === $max ? $len === $min : $len >= $min && $len <= $max;
        };
        static::$methods['int'] = function ($str) {
            return (string)$str === ((string)(int)$str);
        };
        static::$methods['float'] = function ($str) {
            return (string)$str === ((string)(float)$str);
        };
        static::$methods['email'] = function ($str) {
            return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
        };
        static::$methods['url'] = function ($str) {
            return filter_var($str, FILTER_VALIDATE_URL) !== false;
        };
        static::$methods['ip'] = function ($str) {
            return filter_var($str, FILTER_VALIDATE_IP) !== false;
        };
        static::$methods['remoteip'] = function ($str) {
            return filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        };
        static::$methods['alnum'] = function ($str) {
            return ctype_alnum($str);
        };
        static::$methods['alpha'] = function ($str) {
            return ctype_alpha($str);
        };
        static::$methods['contains'] = function ($str, $needle) {
            return strpos($str, $needle) !== false;
        };
        static::$methods['regex'] = function ($str, $pattern) {
            return preg_match($pattern, $str);
        };
        static::$methods['chars'] = function ($str, $chars) {
            return preg_match("/^[$chars]++$/i", $str);
        };

        static::$default_added = true;
    }

    public static function addValidator($method, $callback)
    {
        static::$methods[strtolower($method)] = $callback;
    }

    public function __call($method, $args)
    {
        $reverse = false;
        $validator = $method;
        $method_substr = substr($method, 0, 2);

        if ($method_substr === 'is') {       // is<$validator>()
            $validator = substr($method, 2);
        } elseif ($method_substr === 'no') { // not<$validator>()
            $validator = substr($method, 3);
            $reverse = true;
        }

        $validator = strtolower($validator);

        if (!$validator || !isset(static::$methods[$validator])) {
            throw new BadMethodCallException('Unknown method '. $method .'()');
        }

        $validator = static::$methods[$validator];
        array_unshift($args, $this->str);

        switch (count($args)) {
            case 1:
                $result = $validator($args[0]);
                break;
            case 2:
                $result = $validator($args[0], $args[1]);
                break;
            case 3:
                $result = $validator($args[0], $args[1], $args[2]);
                break;
            case 4:
                $result = $validator($args[0], $args[1], $args[2], $args[3]);
                break;
            default:
                $result = call_user_func_array($validator, $args);
                break;
        }

        $result = (bool)($result ^ $reverse);

        if (false === $this->err) {
            return $result;
        } elseif (false === $result) {
            throw new ValidationException($this->err);
        }

        return $this;
    }
}
