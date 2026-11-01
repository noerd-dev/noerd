<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Noerd\Support\LayoutFields;
use Noerd\Support\LivewireAction;
use Noerd\Traits\NoerdPage;

/**
 * An emptied `type: number` input binds the empty string, not null — Livewire
 * updates bypass the ConvertEmptyStringsToNull middleware — and '' is not a
 * value a numeric column accepts (MySQL: "Incorrect decimal value"). This hook
 * turns every such '' into null right BEFORE store() runs, for the trait's own
 * store() and for any custom override alike, so a component never has to
 * normalise its numeric fields by hand.
 *
 * @internal
 */
final class EmptyNumberFieldHook extends NoerdComponentHook
{
    protected const TRAITS = [NoerdPage::class];

    public function call($method, $params, $returnEarly, $metadata, $componentContext): void
    {
        // An embedded detail saves through the storeDetail-{name} event.
        if (LivewireAction::effectiveMethod($this->component, (string) $method, is_array($params) ? $params : []) !== 'store') {
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
