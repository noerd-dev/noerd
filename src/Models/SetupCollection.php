<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SetupCollection extends Model
{
    protected $guarded = [];

    /**
     * Get all entries for this collection
     */
    public function entries(): HasMany
    {
        return $this->hasMany(SetupCollectionEntry::class)->orderBy('sort');
    }
}
