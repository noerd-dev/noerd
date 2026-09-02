<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use LogicException;
use Noerd\Contracts\DeclaresRelationForms;

/**
 * Stateless mechanics behind declared relation forms (DeclaresRelationForms):
 * resolve a model's declarations, hydrate the form arrays into $detailData,
 * strip them before mass assignment, and persist them after a save. Used by
 * the NoerdDetail trait and the RelationFormPersistHook — never call the
 * pieces per component.
 */
final class RelationFormSync
{
    /**
     * @return array<string, RelationFormDefinition> form key => definition
     */
    public static function forms(string $modelClass): array
    {
        if (! is_a($modelClass, DeclaresRelationForms::class, true)) {
            return [];
        }

        return $modelClass::relationForms();
    }

    /**
     * Whether the ACTIVE layout renders any field of the form (recursing into
     * `type: block`). Persisting a form the layout does not render would
     * overwrite records with stale hydrated values.
     */
    public static function rendered(array $layoutFields, string $formKey): bool
    {
        // The walk stops (returns false) on the first matching field.
        return ! LayoutFields::walk(
            $layoutFields,
            fn(array $field): ?bool => str_starts_with($field['name'] ?? '', 'detailData.' . $formKey . '.') ? false : null,
        );
    }

    /**
     * The form array for an existing owner record, mapped from the related
     * record (all fields null when no related record exists). The relation is
     * unset first — a persister may have changed the FK on this very instance
     * after an earlier eager load.
     *
     * @return array<string, mixed>
     */
    public static function hydrate(Model $owner, RelationFormDefinition $definition): array
    {
        $owner->unsetRelation($definition->relation);
        $related = $owner->{$definition->relation};

        $form = [];
        foreach ($definition->fields as $field) {
            $form[$field] = $related?->{$field};
        }

        return $form;
    }

    /**
     * All declared fields as null — guarantees the parent array exists so a
     * nested wire:model binding never silently drops browser updates.
     *
     * @return array<string, mixed>
     */
    public static function emptyForm(RelationFormDefinition $definition): array
    {
        return array_fill_keys($definition->fields, null);
    }

    /**
     * detailData without the relation form keys AND without the snake_case
     * relation keys an eager load would have left behind (`toArray()` turns
     * `defaultInvoiceAddress` into `default_invoice_address`) — neither may
     * reach mass assignment.
     *
     * @return array<string, mixed>
     */
    public static function strip(string $modelClass, array $detailData): array
    {
        foreach (self::forms($modelClass) as $key => $definition) {
            unset($detailData[$key], $detailData[Str::snake($definition->relation)]);
        }

        return $detailData;
    }

    /**
     * Persist one form onto its owner. Skipped when the form carries no data
     * (persistWhen, default: any declared field non-empty). A custom
     * persistUsing closure owns the write entirely; the default persister
     * normalizes '' to null and updates/creates the related record (BelongsTo
     * additionally links the FK).
     */
    public static function persist(Model $owner, RelationFormDefinition $definition, array $data): bool
    {
        $data = array_intersect_key($data, array_flip($definition->fields));

        if (! self::hasFormData($definition, $data)) {
            return false;
        }

        if ($definition->persistUsing) {
            ($definition->persistUsing)($owner, $data);

            return true;
        }

        self::defaultPersist($owner, $definition, $data);

        return true;
    }

    /**
     * Whether the form carries data worth persisting — persistWhen when
     * declared, otherwise "any declared field is non-empty". Also gates the
     * conditional validateUsing() rules: a form is validated exactly when it
     * would be persisted.
     */
    public static function hasFormData(RelationFormDefinition $definition, array $data): bool
    {
        $data = array_intersect_key($data, array_flip($definition->fields));

        if ($definition->persistWhen) {
            return (bool) ($definition->persistWhen)($data);
        }

        foreach ($data as $value) {
            if (is_string($value) ? mb_trim($value) !== '' : $value !== null) {
                return true;
            }
        }

        return false;
    }

    private static function defaultPersist(Model $owner, RelationFormDefinition $definition, array $data): void
    {
        $data = array_map(
            fn(mixed $value): mixed => is_string($value) && mb_trim($value) === '' ? null : $value,
            $data,
        );

        $relation = $owner->{$definition->relation}();

        if ($relation instanceof BelongsTo) {
            $related = $owner->{$definition->relation};

            if ($related) {
                $related->update($data);
            } else {
                $related = $relation->getRelated()->newQuery()->create($data);
                $relation->associate($related);
                $owner->save();
            }

            return;
        }

        if ($relation instanceof HasOne || $relation instanceof MorphOne) {
            $relation->updateOrCreate([], $data);

            return;
        }

        throw new LogicException(sprintf(
            'Relation form [%s] on [%s] uses an unsupported relation type [%s] — declare a persistUsing closure.',
            $definition->relation,
            $owner::class,
            $relation::class,
        ));
    }
}
