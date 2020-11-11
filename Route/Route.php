<?php

namespace MicroFramework\Route;

use MicroFramework\MicroFramework;

class Route extends MicroFramework
{
    public function get($path = '*', $callback = NULL)
    {
        if(is_string($callback))
        {
            $explode = explode("@", $callback);
            $controller = "App\\Controllers\\".$explode[0];
            $action = $explode[1];
            $this->respond("GET", $path, function($request, 
                                                  $response,
                                                  $app) 
                                                  use ($controller, $action)
                                {
                                    $class = new $controller();
                                    $class->__loadVars($request, $response, 
                                                       $app);
                                    return $class->$action();
                                });
        }
        else
        {
            $this->respond("GET", $path, $callback);
        }
    }
    public function post($path = '*', $callback = NULL)
    {
        if(is_string($callback))
        {
            $explode = explode("@", $callback);
            $controller = "App\\Controllers\\".$explode[0];
            $action = $explode[1];
            $this->respond("POST", $path, function($request, 
                                                  $response,
                                                  $app) 
                                                  use ($controller, $action)
                                {
                                    $class = new $controller();
                                    $class->__loadVars($request, $response, 
                                                       $app);
                                    return $class->$action();
                                });
        }
        else
        {
            $this->respond("POST", $path, $callback);
        }
    }
}