<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * Persists declared relation forms (DeclaresRelationForms) after every detail
 * save, whatever the component's store() does — hand-rolled stores need no
 * relation code. Runs per form only when the ACTIVE layout (including a
 * DB-driven layout override) actually renders the form's fields, so a layout
 * without them never overwrites the record with stale hydrated values.
 * Afterwards the form is rehydrated from the freshly persisted relation, so
 * the fields stay filled in the same response (the persister may have
 * normalized values or swapped the related record).
 */
final class RelationFormPersistHook extends DetailSaveHook
{
    protected function afterSave(Component $component, Model $record): void
    {
        $forms = RelationFormSync::forms($record::class);

        foreach ($forms as $key => $definition) {
            if (! RelationFormSync::rendered($component->pageLayout['fields'] ?? [], $key)) {
                continue;
            }

            $data = $component->detailData[$key] ?? null;
            if (! is_array($data)) {
                continue;
            }

            RelationFormSync::persist($record, $definition, $data);

            $component->detailData[$key] = RelationFormSync::hydrate($record, $definition);
        }
    }
}
