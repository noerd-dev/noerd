<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

function denyObjectWrites(): void
{
    // Read stays undefined (= allowed); only write and delete are denied.
    Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);
    Gate::define(AccessHelper::OBJECT_DELETE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);
}

/** @return array<int, string> full `<input …>` tags bound via wire:model */
function boundInputTags(string $html): array
{
    preg_match_all('/<input\b[^>]*>/s', $html, $matches);

    return array_values(array_filter($matches[0], fn(string $tag): bool => str_contains($tag, 'wire:model')));
}

/** @return array<int, string> full `<select …>` opening tags */
function boundSelectTags(string $html): array
{
    preg_match_all('/<select\b[^>]*>/s', $html, $matches);

    return $matches[0];
}

function tagIsInert(string $tag): bool
{
    // Tailwind variant prefixes (disabled:*) live inside class="…" — strip the
    // class attribute so only real readonly/disabled ATTRIBUTES count.
    $withoutClasses = preg_replace('/\bclass="[^"]*"/s', '', $tag) ?? $tag;

    return (bool) preg_match('/\b(readonly|disabled)\b/', $withoutClasses);
}

it('renders every field editable when writing is allowed', function (): void {
    $html = Livewire::test('noerd-test::write-denied-test')->assertOk()->html();

    foreach (boundInputTags($html) as $tag) {
        expect(tagIsInert($tag))->toBeFalse();
    }
    foreach (boundSelectTags($html) as $tag) {
        expect(tagIsInert($tag))->toBeFalse();
    }

    expect($html)->toContain('wire:click="doSomething"')
        ->toContain('editable: true,');
});

it('renders every field readonly or disabled when writing is denied', function (string $theme): void {
    denyObjectWrites();

    $html = Livewire::test('noerd-test::write-denied-test', ['theme' => $theme])->assertOk()->html();

    $inputs = boundInputTags($html);
    $selects = boundSelectTags($html);

    // Text, checkbox and textarea/richText content controls plus both selects
    // (select + picklist) must all be present — and every one of them inert.
    expect($inputs)->not->toBe([])
        ->and(count($selects))->toBeGreaterThanOrEqual(2);

    foreach ($inputs as $tag) {
        expect(tagIsInert($tag))->toBeTrue();
    }
    foreach ($selects as $tag) {
        expect(tagIsInert($tag))->toBeTrue();
    }

    preg_match_all('/<textarea\b[^>]*>/s', $html, $textareas);
    foreach ($textareas[0] as $tag) {
        expect(tagIsInert($tag))->toBeTrue();
    }

    expect($html)->not->toContain('wire:click="doSomething"')
        ->toContain('editable: false,');
})->with(['default', 'compact', 'numbered']);

it('renders nested block fields readonly when writing is denied', function (): void {
    denyObjectWrites();

    $html = Livewire::test('noerd-test::write-denied-test')->assertOk()->html();

    preg_match('/<input\b[^>]*wire:model="model\.nested"[^>]*>|<input\b[^>]*>(?=[^<]*)/s', $html, $m);
    $nested = array_values(array_filter(
        boundInputTags($html),
        fn(string $tag): bool => str_contains($tag, 'model.nested'),
    ));

    expect($nested)->not->toBe([]);
    foreach ($nested as $tag) {
        expect(tagIsInert($tag))->toBeTrue();
    }
});

it('leaves components without canWriteObject untouched by a denying resolver', function (): void {
    denyObjectWrites();

    // theme-test renders the same detail block but exposes no canWriteObject().
    $html = Livewire::test('noerd-test::theme-test')->assertOk()->html();

    foreach (boundInputTags($html) as $tag) {
        expect(tagIsInert($tag))->toBeFalse();
    }
});

it('keeps an explicit per-field readonly working independently of permissions', function (): void {
    $html = Livewire::test('noerd-test::theme-test', ['fields' => [
        ['name' => 'model.locked', 'label' => 'Locked', 'type' => 'text', 'colspan' => 6, 'readonly' => true],
    ]])->assertOk()->html();

    $tags = boundInputTags($html);
    expect($tags)->not->toBe([]);
    foreach ($tags as $tag) {
        expect(tagIsInert($tag))->toBeTrue();
    }
});
