<?php

namespace Noerd\Services;

use Illuminate\Support\Str;
use Noerd\Support\FieldTypeDefinition;
use Noerd\Support\RelationFieldDefinition;

class RelationFieldRegistry
{
    /** @var array<string, RelationFieldDefinition> */
    private array $definitions = [];

    public function __construct(
        private readonly FieldTypeRegistry $fieldTypeRegistry,
    ) {}

    public function register(string $type, RelationFieldDefinition $definition): void
    {
        $this->definitions[$type] = $definition;

        $this->fieldTypeRegistry->register($type, FieldTypeDefinition::livewire(
            'noerd-relation-field',
            resolver: function (array $field, mixed $component, mixed $detailData, mixed $modelId) use ($type): array {
                $fieldName = $field['name'] ?? '';

                return [
                    'relationType' => $type,
                    'fieldName' => $fieldName,
                    'label' => $field['label'] ?? '',
                    'value' => $this->resolveCurrentValue($fieldName, $component, $detailData),
                    'required' => $field['required'] ?? false,
                    'readonly' => $field['readonly'] ?? false,
                    'modelId' => $modelId,
                ];
            },
            keyResolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): string => $type . '-' . ($field['name'] ?? 'relation') . '-' . ($modelId ?? 'new'),
        ));
    }

    public function has(string $type): bool
    {
        return isset($this->definitions[$type]);
    }

    public function resolve(string $type): ?RelationFieldDefinition
    {
        return $this->definitions[$type] ?? null;
    }

    /**
     * @return array<string, RelationFieldDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    private function resolveCurrentValue(string $fieldName, mixed $component, mixed $detailData): mixed
    {
        if ($fieldName === '') {
            return null;
        }

        if (Str::startsWith($fieldName, 'detailData.')) {
            return data_get($component?->detailData ?? $detailData ?? [], Str::after($fieldName, 'detailData.'));
        }

        $componentValue = data_get($component, $fieldName);
        if ($componentValue !== null) {
            return $componentValue;
        }

        return is_array($detailData) ? data_get($detailData, $fieldName) : null;
    }
}
