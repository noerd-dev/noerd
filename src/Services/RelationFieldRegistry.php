<?php

declare(strict_types=1);

namespace Noerd\Services;

use Closure;
use Illuminate\Support\Str;
use Livewire\Component;
use Noerd\Support\FieldTypeDefinition;
use Noerd\Support\RelationFieldDefinition;

final class RelationFieldRegistry
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
            $definition->fieldComponent ?? 'noerd::noerd-relation-field',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => $this->fieldProps(
                $field,
                $component,
                $detailData,
                $modelId,
                ['relationType' => $type],
            ),
            keyResolver: self::fieldKey($type),
        ));
    }

    /**
     * @param  array<int, string>  $allowedRelationTypes
     */
    public function registerPolymorphic(string $type, array $allowedRelationTypes): void
    {
        $this->fieldTypeRegistry->register($type, FieldTypeDefinition::livewire(
            'noerd::noerd-polymorphic-relation-field',
            resolver: function (array $field, mixed $component, mixed $detailData, mixed $modelId) use ($allowedRelationTypes): array {
                $typeField = $field['typeField'] ?? '';

                return $this->fieldProps($field, $component, $detailData, $modelId, [
                    'typeField' => $typeField,
                    'currentType' => $this->resolveCurrentValue($typeField, $component, $detailData),
                    'allowedTypes' => array_values(array_filter($allowedRelationTypes, fn(string $t): bool => $this->has($t))),
                ]);
            },
            keyResolver: self::fieldKey($type),
        ));
    }

    public function has(string $type): bool
    {
        return isset($this->definitions[$type]);
    }

    public function get(string $type): ?RelationFieldDefinition
    {
        return $this->definitions[$type] ?? null;
    }

    /**
     * The registered relation type behind a foreign-key column, by naming
     * convention: `customer_id` → `customerRelation`, `product_group_id` →
     * `productGroupRelation`. This is the single home of that convention — the
     * `relationBadge` list cell resolves its title through it, and tools that
     * derive fields from the database schema (the Plus layout editor) use it to
     * offer a foreign key as the matching relation field. Null when the column
     * is no `*_id` column or no such type is registered.
     */
    public function typeForColumn(string $column): ?string
    {
        if (! str_ends_with($column, '_id')) {
            return null;
        }

        $type = Str::camel(Str::beforeLast($column, '_id')) . 'Relation';

        return $this->has($type) ? $type : null;
    }

    /**
     * @return array<string, RelationFieldDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * The Livewire key of a relation field: type, field and record.
     */
    private static function fieldKey(string $type): Closure
    {
        return fn(array $field, mixed $component, mixed $detailData, mixed $modelId): string => $type . '-' . ($field['name'] ?? 'relation') . '-' . ($modelId ?? 'new');
    }

    /**
     * The props every relation field component is mounted with — the plain and
     * the polymorphic one differ only in what $extra adds.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function fieldProps(array $field, mixed $component, mixed $detailData, mixed $modelId, array $extra): array
    {
        $fieldName = $field['name'] ?? '';

        $props = [
            ...$extra,
            'fieldName' => $fieldName,
            'label' => $field['label'] ?? '',
            'value' => $this->resolveCurrentValue($fieldName, $component, $detailData),
            'required' => $field['required'] ?? false,
            'readonly' => $field['readonly'] ?? false,
            'helpText' => $field['helpText'] ?? '',
            'modelId' => $modelId,
            'owner' => $component instanceof Component ? $component->getId() : null,
            'errorMessages' => $component instanceof Component ? $component->getErrorBag()->get($fieldName) : [],
        ];

        // Only themes that number their rows put a number on the field.
        if (isset($field['number'])) {
            $props['number'] = (int) $field['number'];
        }

        $props['theme'] = $field['theme'] ?? 'default';

        return $props;
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
