<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

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
final class LockedPropertiesHook extends NoerdComponentHook
{
    protected const TRAITS = [NoerdList::class, NoerdPage::class];

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
        // The ShowFrom/ShowUntil filter columns. Declared protected by
        // convention (then never part of the client payload) — listed here so
        // a public declaration cannot repoint the columns written into the query.
        'showFromDateColumn',
        'showUntilDateColumn',
        // A settings page's model map decides WHICH model persistSettings()
        // writes and WHICH property it reads the payload from — repointing it
        // from the client is an arbitrary-model write.
        'settingsModels',
        // The resolved YAML layout. Written only by initPage()/initDetail()/
        // storeProcess()/QuickCreateExitHook — never by the client. It drives
        // validateFromLayout(), the relation-form persist decision and the
        // embedded list/widget mounts, so a client rewrite means skipped
        // validation and attacker-chosen nested components.
        'pageLayout',
        // Set by the read guard when the object permission denies reading; a
        // client reset would let the page chrome render the denied content.
        'objectReadBlocked',
        // Server-side switches of the list: the CSV export a component enables
        // in mount(), and the widget mode whose row limit bypasses MAX_PER_PAGE.
        'enableCsvExport',
        'minimal',
        'minimalLimit',
        // How the component is embedded — set by the opener's mount arguments
        // or server-side, never by the browser: a picker that stops being one,
        // a compact list that grows its header, an embedded detail that binds
        // the page URL or a quick-create dialog that forgets its exit.
        'compact',
        'multiSelect',
        'returnsSelection',
        'context',
        'showLineNumbers',
        'embedded',
        'quickCreate',
        'disableModal',
        // The URL alias of $modelId (queryStringNoerdPage()).
        'detailPrimary',
        // Declared protected by convention (NoerdPage::canDeleteObject()) —
        // listed so a public declaration cannot be switched back on.
        'deletable',
    ];

    /**
     * The subset that must not arrive as MOUNT ARGUMENTS either.
     *
     * #[Locked] and this hook's update() only veto the update path — Livewire
     * assigns mount parameters to matching public properties before any of that
     * runs (SupportNestingComponents::setParametersToMatchingProperties), and
     * the modal stack takes those arguments straight from the client. These
     * decide WHICH model the generic query reads or a settings page writes,
     * WHICH class the object gates are checked against, the layout and the read
     * guard state, and no legitimate caller passes them — a component declares
     * or resolves them itself. Properties like listActionMethod or
     * context are deliberately NOT here: the relation-field picker passes them.
     *
     * @var array<int, string>
     */
    private const MOUNT_PROTECTED = [
        'detailModel',
        'listModel',
        'objectPermissionModel',
        // Decides which model a settings page loads into the browser and writes.
        'settingsModels',
        'pageLayout',
        // The collection definition a setup-collection entry form renders and
        // writes into — derived from the collection key, never handed in.
        'collectionLayout',
        'objectReadBlocked',
        // The active list view is restored from the URL/session against the
        // views this user may see — a mount argument would skip that check.
        'listView',
        'listViewApp',
        // Set by EmbeddedDetailHostHook from the rendering page.
        'hostId',
        // A component that refuses deletion declares it; no caller re-enables it.
        'deletable',
    ];

    /**
     * @param  array<string, mixed>  $params
     */
    public function mount($params, $parent = null, $attributes = null): void
    {
        foreach (self::MOUNT_PROTECTED as $property) {
            if (array_key_exists($property, $params)) {
                throw new CannotUpdateLockedPropertyException($property);
            }
        }
    }

    public function update($propertyName, $fullPath, $newValue): void
    {
        if (in_array($propertyName, self::PROTECTED_PROPERTIES, true)) {
            throw new CannotUpdateLockedPropertyException($propertyName);
        }
    }
}
