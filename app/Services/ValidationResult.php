<?php

namespace App\Services;

class ValidationResult
{
    public bool $isSuccess;
    public ?string $errorMessage;
    public ?string $confirmationMessage;

    public function __construct()
    {
        $this->isSuccess = false;
        $this->errorMessage = null;
        $this->confirmationMessage = null;
    }

    public static function success(): self
    {
        $result = new self();
        $result->isSuccess = true;
        return $result;
    }

    public static function successWithConfirmation(string $message): self
    {
        $result = new self();
        $result->isSuccess = true;
        $result->confirmationMessage = $message;
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
        return $this->errorMessage ?? $this->confirmationMessage;
    }

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }
}
