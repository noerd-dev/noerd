<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Validation\Rule;

/**
 * One custom attribute a tenant defined for a model: the key inside the
 * `custom_attributes` JSON column, its label, field type and — for a select —
 * its options. Knows the validation rules of a value written without a form.
 */
final readonly class CustomAttributeField
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required = false,
        public array $options = [],
    ) {}

    /**
     * The rules of a value of this field: `required` on create when the field
     * is required, `sometimes` on an update (only the keys sent change).
     *
     * @return list<mixed>
     */
    public function rules(bool $update = false): array
    {
        $presence = match (true) {
            $update => ['sometimes', 'nullable'],
            $this->required => ['required'],
            default => ['nullable'],
        };

        return [...$presence, ...$this->typeRules()];
    }

    /**
     * The option values of a select field.
     *
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_values(array_map(fn(array $option): string => (string) ($option['value'] ?? ''), $this->options));
    }

    /**
     * The field as an API answers it.
     *
     * @return array{key: string, label: string, type: string, required: bool, options?: list<array{value: string, label: string}>}
     */
    public function describe(): array
    {
        $description = [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
        ];

        if ($this->options !== []) {
            $description['options'] = $this->options;
        }

        return $description;
    }

    /**
     * @return list<mixed>
     */
    private function typeRules(): array
    {
        return match ($this->type) {
            'number', 'currency' => ['numeric'],
            'date' => ['date_format:Y-m-d'],
            'checkbox' => ['boolean'],
            'select' => [Rule::in($this->optionValues())],
            default => ['string'],
        };
    }
}
