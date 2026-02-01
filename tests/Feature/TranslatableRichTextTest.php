<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('TranslatableRichText Component', function (): void {

    beforeEach(function (): void {
        $this->admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('displays translatable rich text content from model', function (): void {
        $content = '<p>Test content in German</p>';

        // TipTap editor receives content via Alpine x-data, which is JSON-escaped
        Livewire::test('translatable-rich-text-test', [
            'initialContent' => ['de' => $content, 'en' => 'English content'],
        ])
            ->assertSee('Test content in German', escape: false);
    });

    it('displays content for the selected language from session', function (): void {
        session(['selectedLanguage' => 'en']);

        $germanContent = '<p>German content</p>';
        $englishContent = '<p>English content</p>';

        // TipTap editor receives content via Alpine x-data, which is JSON-escaped
        Livewire::test('translatable-rich-text-test', [
            'initialContent' => ['de' => $germanContent, 'en' => $englishContent],
        ])
            ->assertSee('English content', escape: false)
            ->assertDontSee('German content', escape: false);
    });

    it('defaults to german when no session language is set', function (): void {
        session()->forget('selectedLanguage');

        $germanContent = '<p>German content</p>';
        $englishContent = '<p>English content</p>';

        // TipTap editor receives content via Alpine x-data, which is JSON-escaped
        Livewire::test('translatable-rich-text-test', [
            'initialContent' => ['de' => $germanContent, 'en' => $englishContent],
        ])
            ->assertSee('German content', escape: false);
    });

    it('handles empty content gracefully', function (): void {
        Livewire::test('translatable-rich-text-test', [
            'initialContent' => ['de' => '', 'en' => ''],
        ])
            ->assertSuccessful();
    });

    it('handles missing language key gracefully', function (): void {
        session(['selectedLanguage' => 'fr']);

        Livewire::test('translatable-rich-text-test', [
            'initialContent' => ['de' => 'German', 'en' => 'English'],
        ])
            ->assertSuccessful();
    });
});
