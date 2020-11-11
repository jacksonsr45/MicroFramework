<?php

namespace MicroFramework\DataCollection;

use MicroFramework\Route\FrameworkRoutes;

class RouteCollection extends DataCollection
{
    public function __construct(array $routes = array())
    {
        foreach ($routes as $value) {
            $this->add($value);
        }
    }

    public function set($key, $value)
    {
        if (!$value instanceof FrameworkRoutes) {
            $value = new FrameworkRoutes($value);
        }

        return parent::set($key, $value);
    }

    public function addRoute(FrameworkRoutes $route)
    {
        /**
         * Auto-generate a name from the object's hash
         * This makes it so that we can autogenerate names
         * that ensure duplicate route instances are overridden
         */
        $name = spl_object_hash($route);

        return $this->set($name, $route);
    }

    public function add($route)
    {
        if (!$route instanceof FrameworkRoutes) {
            $route = new FrameworkRoutes($route);
        }

        return $this->addRoute($route);
    }

    
    public function prepareNamed()
    {
        // Create a new collection so we can keep our order
        $prepared = new static();

        foreach ($this as $key => $route) {
            $route_name = $route->getName();

            if (null !== $route_name) {
                // Add the route to the new set with the new name
                $prepared->set($route_name, $route);
            } else {
                $prepared->add($route);
            }
        }

        // Replace our collection's items with our newly prepared collection's items
        $this->replace($prepared->all());

        return $this;
    }
}
