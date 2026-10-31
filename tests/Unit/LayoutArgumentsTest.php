<?php

declare(strict_types=1);

use Noerd\Support\LayoutArguments;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('resolves the modelId and property tokens and passes static values through', function (): void {
    $component = new class {
        public string $accountId = 'acc-7';

        /** @var array<int, string> */
        public array $filters = ['open'];
    };

    $arguments = [
        'modelId' => '$modelId',
        'account' => '$accountId',
        'filters' => '$filters',
        'static' => 'x',
        'number' => 3,
        'unknown' => '$nope',
    ];

    expect(LayoutArguments::resolve($arguments, 42, $component))->toBe([
        'modelId' => 42,
        'account' => 'acc-7',
        'filters' => ['open'],
        'static' => 'x',
        'number' => 3,
        'unknown' => '$nope',
    ])
        // without a component a property token stays a static value
        ->and(LayoutArguments::resolve(['account' => '$accountId'], 42))->toBe(['account' => '$accountId'])
        ->and(LayoutArguments::resolve([], null))->toBe([]);
});
