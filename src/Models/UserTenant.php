<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Model;

class UserTenant extends Model
{
    protected $table = 'users_tenants';
    protected $primaryKey = ['user_id', 'tenant_id'];
}
