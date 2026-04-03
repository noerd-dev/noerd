<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Database\Factories\ProfileFactory;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'noerd_profiles';

    protected $guarded = [];

    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }
}
