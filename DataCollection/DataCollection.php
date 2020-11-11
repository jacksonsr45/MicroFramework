<?php

namespace MicroFramework\DataCollection;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

class DataCollection implements IteratorAggregate, ArrayAccess, Countable
{

    
    protected $attributes = array();
    public function __construct(array $attributes = array())
    {
        $this->attributes = $attributes;
    }

    public function keys($mask = null, $fill_with_nulls = true)
    {
        if (null !== $mask) {
            // Support a more "magical" call
            if (!is_array($mask)) {
                $mask = func_get_args();
            }

            /*
             * Make sure that the returned array has at least the values
             * passed into the mask, since the user will expect them to exist
             */
            if ($fill_with_nulls) {
                $keys = $mask;
            } else {
                $keys = array();
            }

            /*
             * Remove all of the values from the keys
             * that aren't in the passed mask
             */
            return array_intersect(
                array_keys($this->attributes),
                $mask
            ) + $keys;
        }

        return array_keys($this->attributes);
    }

    public function all($mask = null, $fill_with_nulls = true)
    {
        if (null !== $mask) {
            // Support a more "magical" call
            if (!is_array($mask)) {
                $mask = func_get_args();
            }

            /*
             * Make sure that each key in the mask has at least a
             * null value, since the user will expect the key to exist
             */
            if ($fill_with_nulls) {
                $attributes = array_fill_keys($mask, null);
            } else {
                $attributes = array();
            }

            /*
             * Remove all of the keys from the attributes
             * that aren't in the passed mask
             */
            return array_intersect_key(
                $this->attributes,
                array_flip($mask)
            ) + $attributes;
        }

        return $this->attributes;
    }

    public function get($key, $default_val = null)
    {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }

        return $default_val;
    }

    public function set($key, $value)
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function replace(array $attributes = array())
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function merge(array $attributes = array(), $hard = false)
    {
        // Don't waste our time with an "array_merge" call if the array is empty
        if (!empty($attributes)) {
            // Hard merge?
            if ($hard) {
                $this->attributes = array_replace(
                    $this->attributes,
                    $attributes
                );
            } else {
                $this->attributes = array_merge(
                    $this->attributes,
                    $attributes
                );
            }
        }

        return $this;
    }

    public function exists($key)
    {
        // Don't use "isset", since it returns false for null values
        return array_key_exists($key, $this->attributes);
    }

    public function remove($key)
    {
        unset($this->attributes[$key]);
    }

    public function clear()
    {
        return $this->replace();
    }

    public function isEmpty()
    {
        return empty($this->attributes);
    }

    public function cloneEmpty()
    {
        $clone = clone $this;
        $clone->clear();

        return $clone;
    }


    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function __isset($key)
    {
        return $this->exists($key);
    }

    public function __unset($key)
    {
        $this->remove($key);
    }


    public function getIterator()
    {
        return new ArrayIterator($this->attributes);
    }

    public function offsetGet($key)
    {
        return $this->get($key);
    }

    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    public function offsetExists($key)
    {
        return $this->exists($key);
    }

    public function offsetUnset($key)
    {
        $this->remove($key);
    }

    public function count()
    {
        return count($this->attributes);
    }
}
