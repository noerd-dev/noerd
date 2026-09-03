<?php

declare(strict_types=1);

use Noerd\Services\BrandService;
use Noerd\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    // A synthetic palette: the shipped presets are configuration and must not
    // be asserted — what has to hold is how the service resolves them.
    config()->set('noerd.brand.presets', [
        'default' => [
            'brand-bg' => '#ffffff',
            'brand-primary' => '#000000',
        ],
        'zz-sand' => [
            'brand-bg' => '#faf8f4',
            'brand-primary' => '#112233',
        ],
    ]);
    config()->set('noerd.brand.overrides', []);
    config()->set('noerd.brand.active', 'default');
});

describe('colors()', function (): void {
    it('resolves the default preset', function (): void {
        expect(app(BrandService::class)->colors())->toBe([
            'brand-bg' => '#ffffff',
            'brand-primary' => '#000000',
        ]);
    });

    it('resolves the active preset', function (): void {
        config()->set('noerd.brand.active', 'zz-sand');

        expect(app(BrandService::class)->colors())->toBe([
            'brand-bg' => '#faf8f4',
            'brand-primary' => '#112233',
        ]);
    });

    it('falls back to the default preset for an unknown brand', function (): void {
        config()->set('noerd.brand.active', 'zz-does-not-exist');

        expect(app(BrandService::class)->colors())->toBe([
            'brand-bg' => '#ffffff',
            'brand-primary' => '#000000',
        ]);
    });

    it('applies a per-key override on top of the preset', function (): void {
        config()->set('noerd.brand.active', 'zz-sand');
        config()->set('noerd.brand.overrides', ['brand-primary' => '#ff0000']);

        expect(app(BrandService::class)->colors())->toBe([
            'brand-bg' => '#faf8f4',
            'brand-primary' => '#ff0000',
        ]);
    });

    it('ignores an empty or null override', function (mixed $override): void {
        config()->set('noerd.brand.overrides', ['brand-primary' => $override]);

        expect(app(BrandService::class)->colors()['brand-primary'])->toBe('#000000');
    })->with([null, '']);

    it('ignores an override for a key the preset does not declare', function (): void {
        config()->set('noerd.brand.overrides', ['brand-unknown' => '#ff0000']);

        expect(app(BrandService::class)->colors())->not->toHaveKey('brand-unknown');
    });
});

describe('color()', function (): void {
    it('returns a single resolved value', function (): void {
        expect(app(BrandService::class)->color('brand-primary'))->toBe('#000000');
    });

    it('returns null for an unknown key', function (): void {
        expect(app(BrandService::class)->color('brand-unknown'))->toBeNull();
    });
});

describe('cssCustomProperties()', function (): void {
    it('writes one custom property per resolved color, in preset order', function (): void {
        expect(app(BrandService::class)->cssCustomProperties())
            ->toBe('--color-brand-bg: #ffffff; --color-brand-primary: #000000;');
    });

    it('carries the overrides into the CSS', function (): void {
        config()->set('noerd.brand.overrides', ['brand-bg' => '#eeeeee']);

        expect(app(BrandService::class)->cssCustomProperties())
            ->toBe('--color-brand-bg: #eeeeee; --color-brand-primary: #000000;');
    });

    it('is empty when no preset is configured', function (): void {
        config()->set('noerd.brand.presets', []);

        expect(app(BrandService::class)->cssCustomProperties())->toBe('');
    });
});
