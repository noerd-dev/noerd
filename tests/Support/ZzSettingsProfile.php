<?php

declare(strict_types=1);

namespace Noerd\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-scoped fixture model of the settings-page tests — a dedicated zz
 * table, so the tests depend on no real domain model. Internal to this
 * package's suite, not part of the shipped test API.
 */
final class ZzSettingsProfile extends Model
{
    protected $table = 'zz_settings_profiles';

    protected $guarded = [];

    public static function ensureTable(): void
    {
        if (Schema::hasTable('zz_settings_profiles')) {
            return;
        }

        Schema::create('zz_settings_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }
}
