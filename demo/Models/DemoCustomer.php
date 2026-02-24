<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\BelongsToTenant;
use Noerd\Traits\HasListScopes;

class DemoCustomer extends Model
{
    use BelongsToTenant;
    use HasListScopes;

    protected $guarded = [];

    protected array $searchable = [
        'name',
        'company_name',
        'email',
        'zipcode',
    ];
}
