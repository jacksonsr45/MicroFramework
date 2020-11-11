<?php

namespace MicroFramework\Exceptions;

use Exception;
use RuntimeException;
use MicroFramework\Route\FrameworkRoutes;
use Throwable;

/**
 * RoutePathCompilationException
 *
 * Exception used for when a route's path fails to compile
 */
class RoutePathCompilationException extends RuntimeException implements ExceptionInterface
{

    const MESSAGE_FORMAT = 'Route failed to compile with path "%s".';
    const FAILURE_MESSAGE_TITLE_FORMAT = 'Failed with message: "%s"';
    protected $route;
    public static function createFromRoute(FrameworkRoutes $route, $previous = null)
    {
        $error = (null !== $previous) ? $previous->getMessage() : null;
        $code  = (null !== $previous) ? $previous->getCode() : null;

        $message = sprintf(static::MESSAGE_FORMAT, $route->getPath());
        $message .= ' '. sprintf(static::FAILURE_MESSAGE_TITLE_FORMAT, $error);

        $exception = new static($message, $code, $previous);
        $exception->setRoute($route);

        return $exception;
    }

    public function getRoute()
    {
        return $this->route;
    }

    protected function setRoute(Route $route)
    {
        $this->route = $route;

        return $this;
    }
}
