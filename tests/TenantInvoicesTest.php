<?php

use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantInvoice;

uses(Tests\TestCase::class);

it('can create tenant invoice', function (): void {
    $tenantInvoice = TenantInvoice::factory()->create([
        'number' => 'TEST-001',
        'paid' => 0,
    ]);

    expect($tenantInvoice->number)->toBe('TEST-001');
    expect($tenantInvoice->paid)->toBe(0);
});

it('tenant invoice belongs to tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $tenantInvoice = TenantInvoice::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    expect($tenantInvoice->tenant->id)->toBe($tenant->id);
});

it('can identify overdue invoices', function (): void {
    $tenant = Tenant::factory()->create();

    // Create overdue invoice
    TenantInvoice::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => now()->subDays(10),
        'due_date' => now()->subDays(1),
        'paid' => 0,
    ]);

    // Create paid invoice (should not be included)
    TenantInvoice::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => now()->subDays(10),
        'due_date' => now()->subDays(1),
        'paid' => 1,
    ]);

    expect($tenant->dueInvoices)->toHaveCount(1);
});
