<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Page disableModal fallback', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('does not apply the breakout style by default', function (): void {
        Livewire::test('noerd::noerd-users-list')
            ->assertSuccessful()
            ->assertDontSeeHtml('-mx-8');
    });

    it('applies the breakout style when disableModal is set as a mount property', function (): void {
        Livewire::test('noerd::noerd-users-list', ['disableModal' => true])
            ->assertSuccessful()
            ->assertSeeHtml('-mx-8');
    });
});
