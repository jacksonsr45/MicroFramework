<?php


namespace MicroFramework\Factory;
use MicroFramework\Route\FrameworkRoutes;

class RouteFactory extends AbstractRouteFactory
{
    const NULL_PATH_VALUE = '*';
    protected function pathIsNull($path)
    {
        return (static::NULL_PATH_VALUE === $path || null === $path);
    }

    protected function shouldPathStringCauseRouteMatch($path)
    {
        // Only consider a request to be matched when not using 'matchall'
        return !$this->pathIsNull($path);
    }

    protected function preprocessPathString($path)
    {
        // If the path is null, make sure to give it our match-all value
        $path = (null === $path) ? static::NULL_PATH_VALUE : (string) $path;

        // If a custom regular expression (or negated custom regex)
        if ($this->namespace &&
            (isset($path[0]) && $path[0] === '@') ||
            (isset($path[0]) && $path[0] === '!' && isset($path[1]) && $path[1] === '@')
        ) {
            // Is it negated?
            if ($path[0] === '!') {
                $negate = true;
                $path = substr($path, 2);
            } else {
                $negate = false;
                $path = substr($path, 1);
            }

            // Regex anchored to front of string
            if ($path[0] === '^') {
                $path = substr($path, 1);
            } else {
                $path = '.*' . $path;
            }

            if ($negate) {
                $path = '@^' . $this->namespace . '(?!' . $path . ')';
            } else {
                $path = '@^' . $this->namespace . $path;
            }

        } elseif ($this->namespace && $this->pathIsNull($path)) {
            // Empty route with namespace is a match-all
            $path = '@^' . $this->namespace . '(/|$)';
        } else {
            // Just prepend our namespace
            $path = $this->namespace . $path;
        }

        return $path;
    }

    public function build($callback, $path = null, $method = null, $count_match = true, $name = null)
    {
        return new FrameworkRoutes(
            $callback,
            $this->preprocessPathString($path),
            $method,
            $this->shouldPathStringCauseRouteMatch($path) // Ignore the $count_match boolean that they passed
        );
    }
}
