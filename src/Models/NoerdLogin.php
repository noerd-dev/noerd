<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Database\Factories\NoerdLoginFactory;

/**
 * One immutable row per successful authentication on the noerd guard, written
 * by the RecordLogin listener. Rows are never updated, so the model tracks
 * only created_at.
 */
class NoerdLogin extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'noerd_logins';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'user_id');
    }

    public function impersonatedBy(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'impersonated_by_id');
    }

    protected static function newFactory(): NoerdLoginFactory
    {
        return NoerdLoginFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'remember' => 'boolean',
        ];
    }
}
