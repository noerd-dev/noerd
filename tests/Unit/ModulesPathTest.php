<?php

declare(strict_types=1);

use Noerd\Support\ModulesPath;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | Every generator and the YAML resolution build module paths through
 | ModulesPath, so the `noerd.generators.modules_path` setting is honoured by all
 | of them at once.
 */

it('defaults to app-modules below the project root', function (): void {
    config(['noerd.generators.modules_path' => null]);

    expect(ModulesPath::relative())->toBe('app-modules')
        ->and(ModulesPath::absolute())->toBe(base_path('app-modules'))
        ->and(ModulesPath::absolute('crm/src'))->toBe(base_path('app-modules/crm/src'));
});

it('honours the configured modules directory, slashes trimmed', function (): void {
    config(['noerd.generators.modules_path' => '/packages/']);

    expect(ModulesPath::relative())->toBe('packages')
        ->and(ModulesPath::absolute())->toBe(base_path('packages'))
        ->and(ModulesPath::absolute('/crm'))->toBe(base_path('packages/crm'));
});
