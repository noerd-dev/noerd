<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Noerd\Database\Factories\SetupCollectionFactory;

class SetupCollection extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get all entries for this collection
     */
    public function entries(): HasMany
    {
        return $this->hasMany(SetupCollectionEntry::class)->orderBy('sort');
    }

    protected static function newFactory(): SetupCollectionFactory
    {
        return SetupCollectionFactory::new();
    }
}
