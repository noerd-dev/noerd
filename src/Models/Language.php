<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    public $casts = [
        'is_default' => 'boolean',
    ];

    protected $table = 'languages';

    protected $guarded = [];
}
