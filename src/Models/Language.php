<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    public $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $table = 'cms_languages';

    protected $guarded = [];
}
