<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\ComponentHook;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;

/**
 * Rejects client updates to the identity, config and permission properties of
 * noerd list/detail/page components. These are established at mount — the
 * component's own declaration or the opener's arguments — and mutated only
 * server-side, so a client update to them is always tampering: repointing
 * $detailModel/$listModel to an unscoped model, or swapping the modal target,
 * the permission model or the active list view/app.
 *
 * A central hook rather than #[Locked] on each property, because $detailModel,
 * $listModel and $objectPermissionModel are declared per component (with
 * per-component defaults) and cannot carry a trait-level attribute. Mount and
 * server-side writes are untouched — only the client update path is vetoed.
 */
class LockedPropertiesHook extends ComponentHook
{
    /**
     * @var array<int, string>
     */
    private const PROTECTED_PROPERTIES = [
        'detailModel',
        'listModel',
        'objectPermissionModel',
        'detailComponent',
        'detailRoute',
        'listActionMethod',
        'listView',
        'listViewApp',
        'showMoreComponent',
        'showMoreRoute',
        // A settings page's model map decides WHICH model persistSettings()
        // writes and WHICH property it reads the payload from — repointing it
        // from the client is an arbitrary-model write.
        'settingsModels',
        // Selects the list YAML a picker renders; a swapped config exposes
        // columns the intended picker deliberately hides.
        'selectListConfig',
        // The resolved YAML layout. Written only by initPage()/initDetail()/
        // storeProcess()/QuickCreateExitHook — never by the client. It drives
        // validateFromLayout(), the relation-form persist decision and the
        // embedded list/widget mounts, so a client rewrite means skipped
        // validation and attacker-chosen nested components.
        'pageLayout',
    ];

    /**
     * The subset that must not arrive as MOUNT ARGUMENTS either.
     *
     * #[Locked] and this hook's update() only veto the update path — Livewire
     * assigns mount parameters to matching public properties before any of that
     * runs (SupportNestingComponents::setParametersToMatchingProperties), and
     * the modal stack takes those arguments straight from the client. These
     * three decide WHICH model the generic query reads and WHICH class the
     * object gates are checked against, and no legitimate caller passes them —
     * a component declares them itself. Properties like listActionMethod or
     * context are deliberately NOT here: the relation-field picker passes them.
     *
     * @var array<int, string>
     */
    private const MOUNT_PROTECTED = [
        'detailModel',
        'listModel',
        'objectPermissionModel',
    ];

    /**
     * @param  array<string, mixed>  $params
     */
    public function mount($params, $parent = null, $attributes = null): void
    {
        if (! $this->guardsComponent()) {
            return;
        }

        foreach (self::MOUNT_PROTECTED as $property) {
            if (array_key_exists($property, $params)) {
                throw new CannotUpdateLockedPropertyException($property);
            }
        }
    }

    public function update($propertyName, $fullPath, $newValue): void
    {
        if (! in_array($propertyName, self::PROTECTED_PROPERTIES, true)) {
            return;
        }

        if ($this->guardsComponent()) {
            throw new CannotUpdateLockedPropertyException($propertyName);
        }
    }

    /**
     * Whether the current component is one this hook protects.
     */
    private function guardsComponent(): bool
    {
        $traits = class_uses_recursive($this->component);

        return in_array(NoerdList::class, $traits, true) || in_array(NoerdPage::class, $traits, true);
    }
}
