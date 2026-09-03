<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Helpers\ThemeHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The locale and theme resolutions are memoized per request. Invalidation is a
 | model concern (NoerdSettings / UserSetting booted hooks), never something a
 | screen has to remember to call — proven by writing the row directly.
 */

beforeEach(function (): void {
    $this->user = NoerdUser::factory()->adminUser()->create();
    $this->actingAs($this->user);
    $this->tenantId = TenantHelper::getSelectedTenantId();
});

it('sees a saved tenant locale in the same request', function (): void {
    NoerdSettings::query()->updateOrCreate(['tenant_id' => $this->tenantId], ['locale' => 'en-US']);
    expect(FormatHelper::tenantLocale($this->tenantId))->toBe('en-US');

    NoerdSettings::query()->updateOrCreate(['tenant_id' => $this->tenantId], ['locale' => 'de-DE']);

    expect(FormatHelper::tenantLocale($this->tenantId))->toBe('de-DE');
});

it('sees a saved detail theme in the same request', function (): void {
    NoerdSettings::query()->updateOrCreate(['tenant_id' => $this->tenantId], ['detail_theme' => 'default']);
    expect(ThemeHelper::forTenant($this->tenantId)['theme'])->toBe('default');

    NoerdSettings::query()->updateOrCreate(['tenant_id' => $this->tenantId], [
        'detail_theme' => 'compact',
        'detail_theme_enforced' => true,
    ]);

    expect(ThemeHelper::forTenant($this->tenantId))->toBe(['theme' => 'compact', 'enforced' => true]);
});

it('sees a saved user format locale in the same request', function (): void {
    NoerdSettings::query()->updateOrCreate(['tenant_id' => $this->tenantId], ['locale' => 'de-DE']);

    $this->user->setting->update(['format_locale' => 'en-US']);
    expect(FormatHelper::locale())->toBe('en-US');

    $this->user->setting->update(['format_locale' => 'fr-FR']);

    expect(FormatHelper::locale())->toBe('fr-FR');
});
