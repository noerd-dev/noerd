<?php

declare(strict_types=1);

use Livewire\Livewire;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Tests\TestCase;
use Noerd\Tests\Traits\CreatesSetupUser;

uses(TestCase::class);
uses(CreatesSetupUser::class);

/*
 | Saving a collection definition must refresh the list behind the modal. The
 | event name follows the NoerdList listener convention
 | (refreshList-{component}); the previously dispatched 'listRefresh' had no
 | listener anywhere.
 */

beforeEach(function (): void {
    config(['noerd.collections.mode' => 'database']);
    config(['noerd.collections.show_definitions_ui' => true]);
    DatabaseSetupCollectionDefinitionRepository::resetCache();
    app()->forgetInstance(SetupCollectionDefinitionRepositoryContract::class);
    app()->forgetInstance(SetupCollectionHelper::class);
});

it('dispatches an event the definitions list actually listens for', function (): void {
    ['user' => $user] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    $list = Livewire::test('noerd::setup-collection-definitions-list');
    $event = 'refreshList-' . $list->instance()->getName();

    Livewire::test('noerd::setup-collection-definition-detail')
        ->set('detailData.filename', 'zz_refresh_probe')
        ->set('detailData.title', 'Probe')
        ->set('detailData.titleList', 'Probes')
        ->set('fields', [['name' => 'name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6]])
        ->call('store')
        ->assertDispatched($event);

    // …and the list really answers to it.
    $list->dispatch($event)->assertOk();
});
