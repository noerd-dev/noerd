<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Noerd\Database\Factories\UserRoleFactory;
use Noerd\Traits\BelongsToTenant;

class UserRole extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role');
    }

    protected static function newFactory(): UserRoleFactory
    {
        return UserRoleFactory::new();
    }
}
