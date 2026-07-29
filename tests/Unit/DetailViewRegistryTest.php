<?php

use Noerd\Services\DetailViewRegistry;
use Noerd\Support\DetailViewDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class);

describe('DetailViewRegistry', function (): void {

    it('registers the built-in views on boot', function (): void {
        $registry = app(DetailViewRegistry::class);

        expect($registry->has('default'))->toBeTrue()
            ->and($registry->has('compact'))->toBeTrue()
            ->and($registry->has('numbered'))->toBeTrue();
    });

    it('resolves a registered view by name', function (): void {
        $registry = app(DetailViewRegistry::class);

        $numbered = $registry->get('numbered');

        expect($numbered->name)->toBe('numbered')
            ->and($numbered->fullWidthRows)->toBeTrue()
            ->and($numbered->numbersRows)->toBeTrue();
    });

    it('falls back to the default view for unknown or missing names', function (): void {
        $registry = app(DetailViewRegistry::class);

        expect($registry->get('does-not-exist')->name)->toBe('default')
            ->and($registry->get(null)->name)->toBe('default');
    });

    it('lets a later registration override an existing view', function (): void {
        $registry = app(DetailViewRegistry::class);

        $registry->register(new DetailViewDefinition(
            name: 'compact',
            gridClasses: 'custom-classes',
        ));

        expect($registry->get('compact')->gridClasses)->toBe('custom-classes');
    });

    it('allows registering module-defined views', function (): void {
        $registry = app(DetailViewRegistry::class);

        $registry->register(new DetailViewDefinition(
            name: 'table',
            gridClasses: 'py-2 gap-0',
            fullWidthRows: true,
        ));

        expect($registry->has('table'))->toBeTrue()
            ->and($registry->get('table')->fullWidthRows)->toBeTrue();
    });
});
