<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Middleware\SetUserLocale;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\Locales;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    FormatHelper::clearCache();
});

function zzProfileLocaleUser(): NoerdUser
{
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();

    $user->tenants()->attach($tenant->id);
    $user->selected_tenant_id = $tenant->id;
    TenantHelper::setSelectedTenantId($tenant->id);

    return $user;
}

it('shows the resolved locale for a user without one', function (): void {
    $user = zzProfileLocaleUser();
    app()->setLocale('de');

    Livewire::actingAs($user)
        ->test('noerd::profile.update-locale-form')
        ->assertSet('formatLocale', 'de-DE');
});

it('saves the formatting locale and leaves the language untouched', function (): void {
    $user = zzProfileLocaleUser();
    $user->setting->update(['locale' => 'de']);

    Livewire::actingAs($user)
        ->test('noerd::profile.update-locale-form')
        ->set('formatLocale', 'en-US')
        ->call('updateLocale')
        ->assertHasNoErrors()
        ->assertDispatched('locale-updated');

    $user->refresh()->unsetRelation('userSetting');

    expect($user->format_locale)->toBe('en-US')
        ->and($user->locale)->toBe('de');
});

it('rejects a locale outside the fixed list', function (): void {
    $user = zzProfileLocaleUser();

    Livewire::actingAs($user)
        ->test('noerd::profile.update-locale-form')
        ->set('formatLocale', 'xx-XX')
        ->call('updateLocale')
        ->assertHasErrors(['formatLocale']);

    expect($user->fresh()->format_locale)->toBeNull();
});

it('offers exactly the supported locales', function (): void {
    $user = zzProfileLocaleUser();

    $options = Livewire::actingAs($user)
        ->test('noerd::profile.update-locale-form')
        ->get('locales');

    expect(array_column($options, 'value'))->toBe(Locales::SUPPORTED);
});

it('keeps the interface language separate from the formatting locale', function (): void {
    $user = zzProfileLocaleUser();
    $user->setting->update(['locale' => 'de', 'format_locale' => 'en-US']);
    $this->actingAs($user, NoerdAuth::guardName());

    (new SetUserLocale())->handle(request(), fn() => response(''));

    expect(app()->getLocale())->toBe('de')
        ->and(FormatHelper::locale())->toBe('en-US');
});
