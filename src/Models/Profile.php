<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Noerd\Database\Factories\ProfileFactory;

class Profile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }
}
