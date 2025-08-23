<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Model;

class TenantApp extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
