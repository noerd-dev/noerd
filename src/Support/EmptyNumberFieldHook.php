<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\ComponentHook;
use Noerd\Traits\NoerdPage;

/**
 * An emptied `type: number` input binds the empty string, not null — Livewire
 * updates bypass the ConvertEmptyStringsToNull middleware — and '' is not a
 * value a numeric column accepts (MySQL: "Incorrect decimal value"). This hook
 * turns every such '' into null right BEFORE store() runs, for the trait's own
 * store() and for any custom override alike, so a component never has to
 * normalise its numeric fields by hand.
 */
final class EmptyNumberFieldHook extends ComponentHook
{
    public function call($method, $params, $returnEarly, $metadata, $componentContext): void
    {
        if ($method !== 'store') {
            return;
        }

        if (! in_array(NoerdPage::class, class_uses_recursive($this->component), true)) {
            return;
        }

        LayoutFields::walk($this->component->pageLayout['fields'] ?? [], function (array $field): void {
            if (($field['type'] ?? 'text') !== 'number' || ! is_string($field['name'] ?? null)) {
                return;
            }

            if (data_get($this->component, $field['name']) === '') {
                data_set($this->component, $field['name'], null);
            }
        });
    }
}
