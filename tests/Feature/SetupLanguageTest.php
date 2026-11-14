<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Testing\Concerns\CreatesSetupUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, CreatesSetupUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithSetupAccess();

    $this->actingAs($this->user);
});

describe('SetupLanguage Model', function (): void {
    it('creates default languages when none exist', function (): void {
        SetupLanguage::query()->delete();
        SetupLanguage::ensureDefaultLanguagesForTenant($this->tenant->id);

        expect(SetupLanguage::count())->toBe(2);
        expect(SetupLanguage::where('code', 'de')->exists())->toBeTrue();
        expect(SetupLanguage::where('code', 'en')->exists())->toBeTrue();
        expect(SetupLanguage::where('is_default', true)->first()->code)->toBe('en');
    });

    it('returns active languages', function (): void {
        $languages = SetupLanguage::active();

        expect($languages)->toHaveCount(2);
        expect($languages->first()->code)->toBe('en'); // Default first
        expect(SetupLanguage::activeCodes())->toContain('de')->toContain('en');
        expect(SetupLanguage::defaultCode())->toBe('en');
    });
});

describe('SetupLanguage Boot Events', function (): void {
    it('ensures only one default language exists', function (): void {
        // English is default from ensureDefaultLanguagesForTenant
        $english = SetupLanguage::where('code', 'en')->first();
        expect($english->is_default)->toBeTrue();

        // Set German as default
        $german = SetupLanguage::where('code', 'de')->first();
        $german->update(['is_default' => true]);

        // Refresh English from DB
        $english->refresh();

        expect($german->is_default)->toBeTrue();
        expect($english->is_default)->toBeFalse();
        expect(SetupLanguage::where('is_default', true)->count())->toBe(1);
    });

    it('sets new default after deleting default language', function (): void {
        $english = SetupLanguage::where('code', 'en')->first();
        expect($english->is_default)->toBeTrue();

        $english->delete();

        // German should now be default
        $german = SetupLanguage::where('code', 'de')->first();
        expect($german->is_default)->toBeTrue();
    });
});

describe('Setup Languages List Component', function (): void {
    it('shows languages list', function (): void {
        Livewire::test('noerd::setup-languages-list')
            ->assertStatus(200)
            ->assertSee('English');
    });
});

describe('Setup Language Detail Component', function (): void {
    it('loads for new language', function (): void {
        Livewire::test('noerd::setup-language-detail')
            ->assertStatus(200)
            ->assertSet('detailData.is_active', true);
    });

    it('loads existing language', function (): void {
        $english = SetupLanguage::where('code', 'en')->first();

        Livewire::withUrlParams(['setupLanguageId' => $english->id])->test('noerd::setup-language-detail')
            ->assertStatus(200)
            ->assertSet('detailData.code', 'en')
            ->assertSet('detailData.name', 'English');
    });

    it('can save a new language', function (): void {
        Livewire::test('noerd::setup-language-detail')
            ->set('detailData.code', 'fr')
            ->set('detailData.name', 'Français')
            ->set('detailData.is_active', true)
            ->set('detailData.is_default', false)
            ->call('store')
            ->assertSet('showSuccessIndicator', true);

        expect(SetupLanguage::where('code', 'fr')->exists())->toBeTrue();
    });

    it('refuses a code the tenant already uses with a validation error', function (): void {
        Livewire::test('noerd::setup-language-detail')
            ->set('detailData.code', ' de ')
            ->set('detailData.name', 'Deutsch (zweite)')
            ->call('store')
            ->assertHasErrors(['detailData.code' => 'unique']);

        expect(SetupLanguage::where('code', 'de')->count())->toBe(1);
    });

    it('lets an existing language keep its own code and allows a code another tenant uses', function (): void {
        $english = SetupLanguage::where('code', 'en')->first();
        SetupLanguage::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'code' => 'it']);

        Livewire::withUrlParams(['setupLanguageId' => $english->id])->test('noerd::setup-language-detail')
            ->set('detailData.name', 'English (UK)')
            ->call('store')
            ->assertHasNoErrors();

        Livewire::test('noerd::setup-language-detail')
            ->set('detailData.code', 'it')
            ->set('detailData.name', 'Italiano')
            ->call('store')
            ->assertHasNoErrors();

        expect(SetupLanguage::where('code', 'it')->exists())->toBeTrue();
    });
});

describe('Setup Language Switcher', function (): void {
    it('switches to an active language code', function (): void {
        session()->forget(SetupLanguage::SESSION_KEY);

        Livewire::test('noerd::setup-language-switcher')
            ->call('setLanguage', 'de')
            ->assertDispatched('setupLanguageChanged');

        expect(session(SetupLanguage::SESSION_KEY))->toBe('de');
    });

    it('ignores a code that is not an active language', function (): void {
        // Deactivated and never-existing codes are both client input the
        // switcher must not write into the session.
        SetupLanguage::where('code', 'de')->update(['is_active' => false]);
        session([SetupLanguage::SESSION_KEY => 'en']);

        Livewire::test('noerd::setup-language-switcher')
            ->call('setLanguage', 'de')
            ->assertNotDispatched('setupLanguageChanged');

        Livewire::test('noerd::setup-language-switcher')
            ->call('setLanguage', 'zz')
            ->assertNotDispatched('setupLanguageChanged');

        expect(session(SetupLanguage::SESSION_KEY))->toBe('en');
    });
});
