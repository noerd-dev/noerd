<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('renders every profile form inside the shared section chrome', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->create();

    $response = $this->actingAs($user)->get(route('noerd.profile'));

    $response->assertOk();

    // <x-noerd::profile-section> keeps the box chrome and the reading width in
    // one place — every form must still land inside both.
    expect(mb_substr_count($response->getContent(), 'max-w-xl'))->toBeGreaterThanOrEqual(5);
});
