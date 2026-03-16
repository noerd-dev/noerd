<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\BelongsToTenant;

class DemoCustomer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
