<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Support\ServerOnlyMethodsHook;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdComponentShared;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;
use Noerd\Traits\NoerdPositionRow;
use Noerd\Traits\NoerdSettingsPage;
use Noerd\Traits\RoutedModal;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The visibility of the component traits is part of their contract: a helper
 | that only its own trait calls is PRIVATE, a helper the views and a component's
 | own methods call is PROTECTED, and public is reserved for what the browser
 | may run (actions, listeners) and for listData(), which the modules declare
 | public. Livewire offers only public methods as actions, so the visibility —
 | not a list in a hook — is what keeps the browser out.
 */

it('keeps the helpers that only their own trait calls private', function (string $trait, string $method): void {
    expect((new ReflectionMethod($trait, $method))->isPrivate())->toBeTrue();
})->with([
    [NoerdList::class, 'isListColumnField'],
    [NoerdList::class, 'callRowAction'],
    [NoerdList::class, 'listDeclaresBulkAction'],
    [NoerdList::class, 'filterableColumnFields'],
    [NoerdList::class, 'applyColumnFilters'],
    [NoerdList::class, 'objectPermissionModelClass'],
    [NoerdList::class, 'picklistOptionsFromDetail'],
    [NoerdList::class, 'pairedDetailComponent'],
    [NoerdList::class, 'computedColumn'],
    [NoerdList::class, 'computedModelClass'],
    [NoerdList::class, 'fillComputedColumns'],
    [NoerdList::class, 'resolvedModelInstance'],
    [NoerdDetail::class, 'isPicklistOptionsProvider'],
    [NoerdDetail::class, 'writableKeysFromFields'],
    [NoerdDetail::class, 'mountDetailComponent'],
    [NoerdDetail::class, 'computeComputedFields'],
    [NoerdDetail::class, 'applyLayoutDefaults'],
    [NoerdDetail::class, 'ensureRelationFormsHydrated'],
    [NoerdDetail::class, 'ensureCustomAttributesArray'],
    [NoerdPage::class, 'assertDetailPrimaryDeclared'],
    [NoerdPage::class, 'prepareRoutedModal'],
    [NoerdPage::class, 'resolveQuickCreate'],
    [NoerdSettingsPage::class, 'settingsWritableKeys'],
    [NoerdSettingsPage::class, 'settingsModelMap'],
    [NoerdPositionRow::class, 'positionColumnObjects'],
    [RoutedModal::class, 'routedModalArguments'],
]);

it('keeps the helpers the views and components call protected, never public', function (string $trait, string $method): void {
    expect((new ReflectionMethod($trait, $method))->isProtected())->toBeTrue();
})->with([
    [NoerdList::class, 'headerControls'],
    [NoerdList::class, 'hasCollapsibleControls'],
    [NoerdList::class, 'hasHeaderControls'],
    [NoerdList::class, 'isSortableColumn'],
    [NoerdList::class, 'normalizeRecordId'],
    [NoerdList::class, 'visibleRowIds'],
    [NoerdList::class, 'builtListConfig'],
    [NoerdDetail::class, 'validateFromLayout'],
    [NoerdPage::class, 'embeddedDetailComponent'],
    [NoerdPage::class, 'detailTheme'],
    [NoerdPage::class, 'positionColumns'],
    [NoerdPositionRow::class, 'initPositionRow'],
    [NoerdPositionRow::class, 'editablePositionValues'],
    [NoerdPositionRow::class, 'positionColumnCount'],
]);

it('has no public alias of componentName() any more', function (): void {
    expect(method_exists(NoerdComponentShared::class, 'getComponentName'))->toBeFalse();
});

it('lists only existing public methods in the server-only hook', function (): void {
    $methods = (new ReflectionClassConstant(ServerOnlyMethodsHook::class, 'METHODS'))->getValue();

    foreach ($methods as $trait => $names) {
        foreach ($names as $name) {
            $method = new ReflectionMethod($trait, $name);

            expect($method->isPublic())->toBeTrue("{$trait}::{$name} is listed as server-only but is not public — the list is stale.");
        }
    }
});

it('renders a list through its protected helpers while the browser cannot call them', function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    // The list header, the sort buttons and the row ids all go through the
    // protected helpers — the view runs inside the component's scope.
    Livewire::test('noerd::noerd-users-list')->assertOk();

    expect(fn() => Livewire::test('noerd::noerd-users-list')->call('headerControls'))
        ->toThrow(MethodNotFoundException::class);
});
