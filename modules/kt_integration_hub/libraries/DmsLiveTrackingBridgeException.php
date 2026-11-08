<?php

defined('BASEPATH') or exit('No direct script access allowed');

class DmsLiveTrackingBridgeException extends RuntimeException
{
    public function __construct(
        private readonly string $apiCode,
        string $message,
        private readonly int $httpStatus = 409,
        private readonly array $errors = []
    ) {
        parent::__construct($message);
    }

    public function apiCode(): string
    {
        return $this->apiCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
