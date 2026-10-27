<?php

declare(strict_types=1);

namespace Core\Exceptions;

final class ValidationException extends HttpException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(private array $errors)
    {
        parent::__construct(422);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}