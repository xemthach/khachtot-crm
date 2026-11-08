<?php

defined('BASEPATH') or exit('No direct script access allowed');

class SsoTokenException extends RuntimeException
{
    private string $apiCode;

    private int $httpStatus;

    public function __construct(string $apiCode, string $message, int $httpStatus = 400, ?Throwable $previous = null)
    {
        $this->apiCode = $apiCode;
        $this->httpStatus = $httpStatus;

        parent::__construct($message, $httpStatus, $previous);
    }

    public function apiCode(): string
    {
        return $this->apiCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
