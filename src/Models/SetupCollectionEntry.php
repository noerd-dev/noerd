<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Noerd\Services\SetupFieldTypeConverter;
use Noerd\Noerd\Traits\BelongsToTenant;
use Noerd\Noerd\Traits\HasListScopes;

class SetupCollectionEntry extends Model
{
    use BelongsToTenant;
    use HasListScopes;

    protected $guarded = [];

    protected array $searchable = [
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get the parent collection
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(SetupCollection::class, 'setup_collection_id');
    }

    /**
     * Boot method to add model event listeners
     */
    protected static function boot(): void
    {
        parent::boot();

        // Apply field type conversion before saving
        static::saving(function (SetupCollectionEntry $entry): void {
            if ($entry->collection) {
                $collectionKey = mb_strtolower($entry->collection->collection_key);

                if ($entry->data && is_array($entry->data)) {
                    $entry->data = SetupFieldTypeConverter::convertCollectionData($entry->data, $collectionKey);
                }
            }
        });
    }
}
