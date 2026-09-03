<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Database\Factories\UserSettingFactory;
use Noerd\Helpers\FormatHelper;

class UserSetting extends Model
{
    use HasFactory;

    protected $table = 'noerd_user_settings';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'user_id', 'id');
    }

    protected static function booted(): void
    {
        // The formatting locale is memoized per user for the request — a saved
        // or deleted settings row must be visible to the very next format call.
        static::saved(static function (): void {
            FormatHelper::clearCache();
        });
        static::deleted(static function (): void {
            FormatHelper::clearCache();
        });
    }

    protected static function newFactory(): UserSettingFactory
    {
        return UserSettingFactory::new();
    }
}
