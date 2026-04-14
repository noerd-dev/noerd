<?php

namespace Noerd\Services;

use Noerd\Support\FieldTypeDefinition;

class FieldTypeRegistry
{
    /** @var array<string, FieldTypeDefinition> */
    private array $definitions = [];

    public function register(string $type, FieldTypeDefinition $definition): void
    {
        $this->definitions[$type] = $definition;
    }

    public function has(string $type): bool
    {
        return isset($this->definitions[$type]);
    }

    public function resolve(string $type): ?FieldTypeDefinition
    {
        return $this->definitions[$type] ?? null;
    }

    /**
     * @return array<string, FieldTypeDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }
}
