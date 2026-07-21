<?php

namespace App\Data;

use JsonSerializable;

readonly class GeneratedCredential implements JsonSerializable
{
    public function __construct(
        public string $audience,
        public string $name,
        public string $identifier,
        public ?string $email,
        public string $temporaryPassword,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'audience' => $this->audience,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'email' => $this->email,
            'temporary_password' => $this->temporaryPassword,
        ];
    }
}
