<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Traits\BelongsToTenant;

class DemoCategory extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function demoCustomers(): HasMany
    {
        return $this->hasMany(DemoCustomer::class);
    }
}
