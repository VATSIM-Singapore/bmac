<?php

namespace App\Services;

class ValidationResult
{
    public bool $isSuccess;
    public ?string $errorMessage;

    public function __construct()
    {
        $this->isSuccess = false;
        $this->errorMessage = null;
    }

    public static function success(): self
    {
        $result = new self();
        $result->isSuccess = true;
        return $result;
    }



    public static function error(string $message): self
    {
        $result = new self();
        $result->isSuccess = false;
        $result->errorMessage = $message;
        return $result;
    }

    public function getMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }
}
