<?php

namespace Nywerk\Noerd\Models;

use Database\Factories\UserRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): UserRoleFactory
    {
        return UserRoleFactory::new();
    }
}
