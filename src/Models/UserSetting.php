<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Database\Factories\UserSettingFactory;

class UserSetting extends Model
{
    use HasFactory;

    protected $table = 'noerd_user_settings';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(NoerdUser::class, 'user_id', 'id');
    }

    protected static function newFactory(): UserSettingFactory
    {
        return UserSettingFactory::new();
    }
}
