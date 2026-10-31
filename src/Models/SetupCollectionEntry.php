<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Services\SetupFieldTypeConverter;
use Noerd\Support\TenantContext;
use Noerd\Traits\BelongsToTenant;

class SetupCollectionEntry extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

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
    protected static function booted(): void
    {
        // Apply field type conversion before saving — in the entry's own tenant:
        // the collection and the active languages are tenant-scoped, and a
        // seeder or migration saves entries without a signed-in user.
        static::saving(function (SetupCollectionEntry $entry): void {
            $convert = function () use ($entry): void {
                if ($entry->collection) {
                    $collectionKey = mb_strtolower($entry->collection->collection_key);

                    if ($entry->data && is_array($entry->data)) {
                        $entry->data = SetupFieldTypeConverter::convertCollectionData($entry->data, $collectionKey);
                    }
                }
            };

            $entry->tenant_id ? TenantContext::run((int) $entry->tenant_id, $convert) : $convert();
        });

        // The picklist options built from these entries are memoized per
        // request — a written entry must be visible to the next render.
        static::saved(static function (): void {
            SetupCollectionHelper::clearSelectOptionsCache();
        });
        static::deleted(static function (): void {
            SetupCollectionHelper::clearSelectOptionsCache();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
