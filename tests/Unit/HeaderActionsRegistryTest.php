<?php

use Noerd\Services\HeaderActionsRegistry;

it('starts empty so the core renders no module partials of its own', function (): void {
    expect((new HeaderActionsRegistry())->all())->toBe([]);
});

it('returns registered views in registration order', function (): void {
    $registry = new HeaderActionsRegistry();

    $registry->register('some-module::partials.first');
    $registry->register('other-module::partials.second');

    expect($registry->all())->toBe([
        'some-module::partials.first',
        'other-module::partials.second',
    ]);
});
