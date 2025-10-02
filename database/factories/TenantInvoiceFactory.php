<?php

namespace Noerd\Noerd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantInvoice;

class TenantInvoiceFactory extends Factory
{
    protected $model = TenantInvoice::class;

    public function definition(): array
    {
        return [
            'number' => $this->faker->numberBetween(1000, 9999),
            'lines' => '[]',
            'customer_name' => $this->faker->name,
            'hash' => $this->faker->uuid,
            'date' => now(),
            'due_date' => now()->addDays(14),
            'paid' => 0,
            'total_gross_amount' => $this->faker->randomFloat(2, 10, 1000),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
