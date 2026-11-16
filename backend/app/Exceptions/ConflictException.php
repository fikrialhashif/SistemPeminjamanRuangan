<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ConflictException extends HttpException
{
    public function __construct(string $message = 'Resource conflict', ?\Throwable $previous = null)
    {
        parent::__construct(409, $message, $previous);
    }
}
