<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('creates a new tenant with command options', function (): void {
    $this->artisan('noerd:make-tenant', [
        '--name' => 'New Tenant',
    ])->assertExitCode(0);

    // Verify tenant was created
    $tenant = Tenant::where('name', 'New Tenant')->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->uuid)->not->toBeNull();
});

it('generates unique uuid for each tenant', function (): void {
    $this->artisan('noerd:make-tenant', ['--name' => 'Tenant 1'])->assertExitCode(0);
    $this->artisan('noerd:make-tenant', ['--name' => 'Tenant 2'])->assertExitCode(0);

    $tenant1 = Tenant::where('name', 'Tenant 1')->first();
    $tenant2 = Tenant::where('name', 'Tenant 2')->first();

    expect($tenant1->uuid)->not->toBe($tenant2->uuid);
});

it('rejects an invalid tenant name', function (string $name, string $error): void {
    // The error message is the behaviour: it tells the operator what to fix.
    $this->artisan('noerd:make-tenant', ['--name' => $name])
        ->expectsOutput($error)
        ->assertExitCode(1);

    expect(Tenant::where('name', $name)->exists())->toBeFalse();
})->with([
    'shorter than 3 characters' => ['AB', 'Tenant name must be at least 3 characters.'],
    'longer than 50 characters' => [str_repeat('A', 51), 'Tenant name must be at most 50 characters.'],
]);
