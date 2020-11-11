<?php

namespace MicroFramework\Exceptions;

use RuntimeException;

/**
 * RegularExpressionCompilationException
 *
 * Exception used for when a regular expression fails to compile
 */
class RegularExpressionCompilationException extends RuntimeException implements ExceptionInterface
{
}
