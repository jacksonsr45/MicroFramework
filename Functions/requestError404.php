<?php

use MicroFramework\MicroFramework;

$base = dirname($_SERVER['PHP_SELF']);

// Update request when we have a subdirectory    
if(ltrim($base, '/')){ 

    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($base));
}

// Dispatch as always
$MicroFramework = new MicroFramework();
$MicroFramework->dispatch();

$status_code = http_response_code();
$path_views = "/../../../App/Views/";

switch ($status_code) {
    case '400':
        include_once __DIR__.$path_views.$_ENV['PAGE_400'];
        break;
    case '401':
        include_once __DIR__.$path_views.$_ENV['PAGE_401'];
        break;
    case '403':
        include_once __DIR__.$path_views.$_ENV['PAGE_403'];
        break;
    case '404':
        include_once __DIR__.$path_views.$_ENV['PAGE_404'];
        break;
    case '500':
        include_once __DIR__.$path_views.$_ENV['PAGE_500'];
        break;
    default:
        break;
}