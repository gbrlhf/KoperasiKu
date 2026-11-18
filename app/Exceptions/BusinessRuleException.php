<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception aturan bisnis yang pesannya aman dikembalikan ke client/API.
 */
class BusinessRuleException extends Exception
{
    public string $errorCode;

    public function __construct(string $message, int $httpStatus = 422, string $errorCode = 'BUSINESS_RULE_VIOLATION')
    {
        parent::__construct($message, $httpStatus);
        $this->errorCode = $errorCode;
    }

    public function getHttpStatus(): int
    {
        $code = (int) $this->getCode();
        return ($code >= 400 && $code < 600) ? $code : 422;
    }
}
