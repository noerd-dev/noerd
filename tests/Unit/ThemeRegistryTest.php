<?php

declare(strict_types=1);

use Noerd\Services\ThemeRegistry;
use Noerd\Support\ThemeDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class);

describe('ThemeRegistry', function (): void {

    it('discovers the built-in themes from their folders', function (): void {
        $registry = app(ThemeRegistry::class);

        expect($registry->has('default'))->toBeTrue()
            ->and($registry->has('compact'))->toBeTrue()
            ->and($registry->has('numbered'))->toBeTrue();
    });

    it('resolves a discovered theme by name', function (): void {
        $registry = app(ThemeRegistry::class);

        $numbered = $registry->get('numbered');

        expect($numbered->name)->toBe('numbered')
            ->and($numbered->fullWidthRows)->toBeTrue()
            ->and($numbered->numbersRows)->toBeTrue();
    });

    it('falls back to the default theme for unknown or missing names', function (): void {
        $registry = app(ThemeRegistry::class);

        expect($registry->get('does-not-exist')->name)->toBe('default')
            ->and($registry->get(null)->name)->toBe('default');
    });

    it('lets a programmatic registration override a discovered theme', function (): void {
        $registry = app(ThemeRegistry::class);

        $registry->register(new ThemeDefinition(
            name: 'compact',
            gridClasses: 'custom-classes',
        ));

        expect($registry->get('compact')->gridClasses)->toBe('custom-classes');
    });

    it('exposes position table styling for every built-in theme', function (): void {
        $registry = app(ThemeRegistry::class);

        expect($registry->get('default')->controlClasses)->toContain('h-10')
            ->and($registry->get('compact')->controlClasses)->toContain('h-7')
            ->and($registry->get('numbered')->controlClasses)->toContain('h-9')
            ->and($registry->get('numbered')->rowClasses)->toContain('bg-zinc-100')
            ->and($registry->get('numbered')->tableClasses)->toContain('border-separate')
            ->and($registry->get('default')->sectionPadding)->toBe('py-8')
            ->and($registry->get('compact')->sectionPadding)->toBe('py-3');
    });

    it('keeps the position styling optional for programmatic themes', function (): void {
        $registry = app(ThemeRegistry::class);

        $registry->register(new ThemeDefinition(
            name: 'bare',
            gridClasses: 'py-2',
        ));

        expect($registry->get('bare')->controlClasses)->toBe($registry->get('default')->controlClasses)
            ->and($registry->get('bare')->sectionPadding)->toBe('py-8');
    });

    it('exposes the display label from theme.yml', function (): void {
        $registry = app(ThemeRegistry::class);

        expect($registry->get('compact')->label)->toBe('Compact')
            ->and($registry->get('numbered')->label)->toBe('Numbered');
    });

    it('hydrates a definition from an array with defaults for missing keys', function (): void {
        $definition = ThemeDefinition::fromArray('custom', [
            'label' => 'Custom',
            'gridClasses' => 'py-1',
            'numbersRows' => true,
            'buttonClasses' => 'h-7 text-xs',
        ]);

        expect($definition->name)->toBe('custom')
            ->and($definition->label)->toBe('Custom')
            ->and($definition->gridClasses)->toBe('py-1')
            ->and($definition->numbersRows)->toBeTrue()
            ->and($definition->fullWidthRows)->toBeFalse()
            ->and($definition->buttonClasses)->toBe('h-7 text-xs')
            ->and($definition->spacerClass)->toBe('h-16')
            ->and($definition->controlClasses)->toContain('h-10');
    });
});
