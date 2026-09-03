<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\TenantApp;
use Noerd\Models\UserSetting;
use Noerd\Support\Locales;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | A factory's default definition() must produce a fully valid, persistable
 | record — every scalar column non-null and deterministic — so a test can seed
 | one without knowing which columns a form marks required.
 */

it('persists a tenant app from its factory and finds it case-insensitively', function (): void {
    $app = TenantApp::factory()->create();

    expect($app->exists)->toBeTrue()
        ->and($app->name)->toBe(mb_strtoupper($app->name))
        ->and($app->is_active)->toBeTrue()
        ->and(TenantApp::query()->namedAny([mb_strtolower($app->name)])->pluck('id')->all())->toBe([$app->id]);

    expect(TenantApp::factory()->inactive()->create()->is_active)->toBeFalse();
});

it('persists deterministic tenant settings from the factory', function (): void {
    $settings = NoerdSettings::factory()->create();

    expect($settings->currency)->toBe(CurrencyHelper::DEFAULT_CURRENCY)
        ->and($settings->locale)->toBe(Locales::DEFAULT)
        ->and($settings->tenant_id)->not->toBeNull();

    $custom = NoerdSettings::factory()->withCurrency('CHF')->withLocale('de-CH')->create();

    expect($custom->currency)->toBe('CHF')
        ->and($custom->locale)->toBe('de-CH');
});

it('gives a user settings row a valid formatting locale by default', function (): void {
    $setting = UserSetting::factory()->create();

    expect(Locales::isSupported($setting->format_locale))->toBeTrue();

    // "No preference" stays an explicit state.
    expect(UserSetting::factory()->withFormatLocale(null)->create()->format_locale)->toBeNull();
});

it('marks a user as super admin through the factory state', function (): void {
    expect(NoerdUser::factory()->superAdmin()->create()->fresh()->isSuperAdmin())->toBeTrue()
        ->and(NoerdUser::factory()->create()->fresh()->isSuperAdmin())->toBeFalse();

    // The column itself stays guarded against mass assignment from a request.
    $user = NoerdUser::factory()->create();
    $user->fill(['super_admin' => true])->save();

    expect($user->fresh()->isSuperAdmin())->toBeFalse();
});
