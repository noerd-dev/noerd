<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Noerd\Traits\BelongsToTenant;

class DemoTag extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function demoCustomers(): BelongsToMany
    {
        return $this->belongsToMany(DemoCustomer::class);
    }
}
