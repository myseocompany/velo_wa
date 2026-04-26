<?php

declare(strict_types=1);

namespace App\Exceptions;

class AiProviderException extends \RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly int $status,
        public readonly ?string $body,
        string $message,
    ) {
        parent::__construct($message);
    }
}
