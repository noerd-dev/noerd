<?php

use Noerd\Helpers\KeyboardShortcutHelper;

uses(Tests\TestCase::class);

it('provides default keyboard shortcuts via module config', function (): void {
    expect(config('noerd.keyboard_shortcuts'))->toBe([
        'search_focus' => 's',
        'new_entry' => 'n',
        'save' => 'ctrl+enter',
        'delete' => 'ctrl+backspace',
    ]);
});

it('returns correct JS and badge for simple key shortcut', function (): void {
    config()->set('noerd.keyboard_shortcuts.search_focus', 's');

    $result = KeyboardShortcutHelper::parse('search_focus', 's');

    expect($result)->toHaveKeys(['js', 'badge'])
        ->and($result['js'])->toContain("e.key.toLowerCase() === \"s\"")
        ->and($result['js'])->toContain('INPUT')
        ->and($result['badge'])->toBe('s');
});

it('returns correct JS and badge for modifier key shortcut', function (): void {
    config()->set('noerd.keyboard_shortcuts.save', 'ctrl+enter');

    $result = KeyboardShortcutHelper::parse('save', 'ctrl+enter');

    expect($result['js'])->toContain("e.key.toLowerCase() === \"enter\"")
        ->and($result['js'])->toContain('e.ctrlKey || e.metaKey')
        ->and($result['js'])->not->toContain('INPUT');
});

it('uses fallback default when config key is missing', function (): void {
    config()->set('noerd.keyboard_shortcuts', []);

    $result = KeyboardShortcutHelper::parse('search_focus', 's');

    expect($result['badge'])->toBe('s');
});

it('generates badge with key symbol for special keys', function (): void {
    config()->set('noerd.keyboard_shortcuts.delete', 'ctrl+backspace');

    $badge = KeyboardShortcutHelper::toBadge('delete', 'ctrl+backspace');

    expect($badge)->toContain('⌫');
});

it('generates JS expression via toJs method', function (): void {
    config()->set('noerd.keyboard_shortcuts.new_entry', 'n');

    $js = KeyboardShortcutHelper::toJs('new_entry', 'n');

    expect($js)->toContain("e.key.toLowerCase() === \"n\"")
        ->and($js)->toContain('INPUT');
});

it('allows project config to override module defaults', function (): void {
    config()->set('noerd.keyboard_shortcuts.search_focus', '/');

    $result = KeyboardShortcutHelper::parse('search_focus', 's');

    expect($result['badge'])->toBe('/');
});
