<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Noerd\Database\Factories\UserSettingFactory;

class UserSetting extends Model
{
    use HasFactory;

    protected $table = 'user_settings';

    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'selected_tenant_id',
        'selected_app',
        'locale',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function selectedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'selected_tenant_id', 'id');
    }

    protected static function newFactory(): UserSettingFactory
    {
        return UserSettingFactory::new();
    }
}
