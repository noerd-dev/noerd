<?php

declare(strict_types=1);

namespace Noerd\Support;

use Closure;

/**
 * Declares one relation form of a model (see DeclaresRelationForms): which
 * Eloquent relation backs the YAML form key, which fields the form carries,
 * and optionally how it is persisted. Definitions are built fresh on every
 * relationForms() call and never stored in Livewire state, so the closures
 * carry no serialization hazard.
 */
final class RelationFormDefinition
{
    private function __construct(
        public readonly string $relation,
        public readonly array $fields,
        public ?Closure $persistUsing = null,
        public ?Closure $persistWhen = null,
        public array $rules = [],
        public array $messages = [],
    ) {}

    /**
     * @param  string  $relation  Eloquent relation method, e.g. 'defaultInvoiceAddress'
     * @param  array<int, string>  $fields  attribute names hydrated from / persisted to the related model
     */
    public static function make(string $relation, array $fields): self
    {
        return new self($relation, $fields);
    }

    /**
     * Custom persister: fn (Model $owner, array $data): void. Replaces the
     * default persistence entirely — including its ''-to-null normalization.
     */
    public function persistUsing(Closure $callback): self
    {
        $this->persistUsing = $callback;

        return $this;
    }

    /**
     * Custom "has data" check: fn (array $data): bool. Defaults to "any
     * declared field is non-empty".
     */
    public function persistWhen(Closure $callback): self
    {
        $this->persistWhen = $callback;

        return $this;
    }

    /**
     * Validation applied by validateFromLayout() exactly when the form would be
     * persisted (see persistWhen). Rules and message keys use the bare field
     * names (e.g. 'iban' / 'iban.required') — the framework prefixes them with
     * the full detailData path. Rule objects are fine: definitions are rebuilt
     * per call and never serialized.
     *
     * @param  array<string, mixed>  $rules  field => rules
     * @param  array<string, string>  $messages  'field.rule' => message
     */
    public function validateUsing(array $rules, array $messages = []): self
    {
        $this->rules = $rules;
        $this->messages = $messages;

        return $this;
    }
}
