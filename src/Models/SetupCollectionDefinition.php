<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Traits\BelongsToTenant;

class SetupCollectionDefinition extends Model
{
    use BelongsToTenant;

    protected $table = 'setup_collection_definitions';

    protected $guarded = [];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'fields' => 'array',
        ];
    }

    protected function key(): Attribute
    {
        return Attribute::make(
            set: fn(string $value) => mb_strtoupper($value),
        );
    }

    protected function filename(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                $value = mb_strtolower($value);
                $value = preg_replace('/\.ya?ml$/i', '', $value);
                $value = str_replace('-', '_', $value);

                return preg_replace('/[^a-z0-9_]/', '', $value);
            },
        );
    }
}
