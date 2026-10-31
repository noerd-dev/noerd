<?php

declare(strict_types=1);

use Noerd\Services\ThemeRegistry;
use Noerd\Support\ThemeDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | The keys of a theme.yml are the theme contract — a module's position rows
 | read `controlSize` for their icon buttons, so the key stays on the definition
 | although the core itself never reads it.
 */

it('keeps the control size of the position-row buttons on the definition', function (): void {
    expect((new ThemeDefinition('zz'))->controlSize)->toBe('md')
        ->and(ThemeDefinition::fromArray('zz', ['controlSize' => 'sm'])->controlSize)->toBe('sm')
        ->and(ThemeDefinition::fromArray('zz', ['controlSize' => ''])->controlSize)->toBe('md')
        ->and(ThemeDefinition::fromArray('zz', [])->controlSize)->toBe('md');
});

it('ships the control size with every built-in theme', function (): void {
    $registry = app(ThemeRegistry::class);

    expect($registry->get('compact')->controlSize)->toBe('sm')
        ->and($registry->get('default')->controlSize)->toBe('md')
        ->and($registry->get('numbered')->controlSize)->toBe('md');
});
