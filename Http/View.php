<?php

namespace MicroFramework\Http;

use MicroFramework\Provider\ServiceProvider;

class View extends ServiceProvider
{
    public function render($view, array $data = array())
    {
        $_view_path = "../App/Views/";
        parent::render($_view_path.$view, $data);
    }
}