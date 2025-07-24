<?php

namespace Nywerk\Noerd\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }
}
