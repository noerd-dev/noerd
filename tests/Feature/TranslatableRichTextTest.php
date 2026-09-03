<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupLanguage;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The options the TipTap Alpine component is initialised with.
 *
 * @return array<string, mixed>|null
 */
function zzTiptapOptions(string $html): ?array
{
    if (preg_match("/noerdTiptap\\(JSON\\.parse\\('(.*?)'\\)\\)/s", $html, $matches) !== 1) {
        return null;
    }

    // @js() emits JSON.parse('…') with the payload escaped as a JS string
    // literal (\u0022 for every quote) — resolve that layer first.
    $json = json_decode('"' . $matches[1] . '"');

    return is_string($json) ? json_decode($json, true) : null;
}

describe('TranslatableRichText Component', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('displays translatable rich text content from model', function (): void {
        session([SetupLanguage::SESSION_KEY => 'de']);
        $content = '<p>Test content in German</p>';

        // TipTap editor receives content via Alpine x-data, which is JSON-escaped
        Livewire::test('noerd-test::translatable-rich-text-test', [
            'initialContent' => ['de' => $content, 'en' => 'English content'],
        ])
            ->assertSee('Test content in German', escape: false);
    });

    it('displays content for the selected language from session', function (): void {
        session([SetupLanguage::SESSION_KEY => 'en']);

        $germanContent = '<p>German content</p>';
        $englishContent = '<p>English content</p>';

        // TipTap editor receives content via Alpine x-data, which is JSON-escaped
        Livewire::test('noerd-test::translatable-rich-text-test', [
            'initialContent' => ['de' => $germanContent, 'en' => $englishContent],
        ])
            ->assertSee('English content', escape: false)
            ->assertDontSee('German content', escape: false);
    });

    it('falls back to the tenant default language when no session language is set', function (): void {
        session()->forget(SetupLanguage::SESSION_KEY);
        SetupLanguage::where('code', 'de')->update(['is_default' => true]);
        SetupLanguage::where('code', 'en')->update(['is_default' => false]);

        $germanContent = '<p>German content</p>';
        $englishContent = '<p>English content</p>';

        // TipTap editor receives content via Alpine x-data, which is JSON-escaped
        Livewire::test('noerd-test::translatable-rich-text-test', [
            'initialContent' => ['de' => $germanContent, 'en' => $englishContent],
        ])
            ->assertSee('German content', escape: false)
            ->assertDontSee('English content', escape: false);
    });

    it('handles empty content gracefully', function (): void {
        $html = Livewire::test('noerd-test::translatable-rich-text-test', [
            'initialContent' => ['de' => '', 'en' => ''],
        ])
            ->assertSuccessful()
            ->html();

        // The editor starts on the empty string, not on a null/undefined value.
        expect(zzTiptapOptions($html))->not->toBeNull()
            ->and(zzTiptapOptions($html)['content'])->toBe('');
    });

    it('handles missing language key gracefully', function (): void {
        session([SetupLanguage::SESSION_KEY => 'fr']);

        $html = Livewire::test('noerd-test::translatable-rich-text-test', [
            'initialContent' => ['de' => 'German', 'en' => 'English'],
        ])
            ->assertSuccessful()
            ->html();

        // A language the value does not carry renders empty — no other
        // language's content may leak into the editor.
        expect(zzTiptapOptions($html))->not->toBeNull()
            ->and(zzTiptapOptions($html)['content'])->toBe('')
            ->not->toContain('German')
            ->not->toContain('English');
    });
});
