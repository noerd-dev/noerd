<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Noerd\Database\Factories\SettingFactory;

class Setting extends Model
{
    use HasFactory;

    protected $primaryKey = 'tenant_id';
    protected $guarded = ['id'];

    protected $hidden = [
        'updated_at',
        'smtp_host',
        'smtp_username',
        'smtp_password',
        'smtp_port',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }
}
