<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('pads the page body vertically for detail components regardless of rendered children', function (): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();

    $this->actingAs($admin);

    assertElementHasClasses(
        Livewire::test('noerd::noerd-user-detail')->html(),
        ['flex-1', 'min-h-0', 'px-6', 'overflow-y-auto', 'pt-6', 'pb-8'],
    );
});

it('keeps list bodies unpadded — lists own their internal spacing', function (): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();

    $this->actingAs($admin);

    assertNoElementHasClasses(
        Livewire::test('noerd::noerd-users-list')->html(),
        ['overflow-y-auto', 'pt-6', 'pb-8'],
    );
});

it('pads a plain page body and supports the bodyPadding opt-out', function (): void {
    assertElementHasClasses(
        Blade::render('<x-noerd::page>Body</x-noerd::page>'),
        ['flex-1', 'min-h-0', 'px-6', 'overflow-y-auto', 'pt-6', 'pb-8'],
    );

    assertNoElementHasClasses(
        Blade::render('<x-noerd::page :bodyPadding="false">Body</x-noerd::page>'),
        ['overflow-y-auto', 'pt-6'],
    );
});
