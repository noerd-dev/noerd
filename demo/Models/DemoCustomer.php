<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Noerd\Traits\BelongsToTenant;

class DemoCustomer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DemoCategory::class, 'demo_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DemoTag::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'revenue' => 'decimal:2',
            'contract_start' => 'date',
            'custom_attributes' => 'array',
        ];
    }
}
