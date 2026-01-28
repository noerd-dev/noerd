<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Database\Factories\UserRoleFactory;

class UserRole extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): UserRoleFactory
    {
        return UserRoleFactory::new();
    }
}
