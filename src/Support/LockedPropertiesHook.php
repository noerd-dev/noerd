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

    public function update($propertyName, $fullPath, $newValue): void
    {
        if (! in_array($propertyName, self::PROTECTED_PROPERTIES, true)) {
            return;
        }

        $traits = class_uses_recursive($this->component);

        if (in_array(NoerdList::class, $traits, true) || in_array(NoerdPage::class, $traits, true)) {
            throw new CannotUpdateLockedPropertyException($propertyName);
        }
    }
}
