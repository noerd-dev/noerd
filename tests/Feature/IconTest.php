<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Helpers\IconHelper;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('icon helper', function (): void {

    it('enumerates all outline heroicons sorted', function (): void {
        // Which icons the heroicons package ships is its business — what this
        // helper owns is handing them out as a sorted list of names.
        $icons = IconHelper::heroicons();

        expect($icons)->toBeArray()
            ->and($icons)->not->toBeEmpty()
            ->and($icons)->each->toBeString();

        $sorted = $icons;
        sort($sorted);
        expect($icons)->toBe($sorted);
    });
});

describe('icon picker', function (): void {

    it('writes the chosen icon to the parent detail and closes the modal', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();

        Livewire::actingAs($user)
            ->test('noerd::icon-picker', ['context' => 'detailData.icon'])
            ->call('selectIcon', 'trophy')
            ->assertDispatched('setFieldValue', field: 'detailData.icon', value: 'trophy')
            ->assertDispatched('closeTopModal');
    });

    it('filters the icon list on the server', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();

        $component = Livewire::actingAs($user)
            ->test('noerd::icon-picker', ['context' => 'detailData.icon'])
            ->set('search', 'Shopping cart');

        $filtered = $component->instance()->filteredIcons();

        expect($filtered)->toContain('shopping-cart')
            ->and($filtered)->not->toContain('trophy')
            ->and(count($filtered))->toBeLessThan(count(IconHelper::heroicons()));
    });

    it('refuses an icon name that is not a heroicon', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();

        Livewire::actingAs($user)
            ->test('noerd::icon-picker', ['context' => 'detailData.icon'])
            ->call('selectIcon', '../../etc/passwd')
            ->assertStatus(422);
    });
});
