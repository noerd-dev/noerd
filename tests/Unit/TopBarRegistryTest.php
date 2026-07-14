<?php

use Noerd\Services\TopBarRegistry;

it('starts empty so the core renders no module components of its own', function (): void {
    expect((new TopBarRegistry())->all())->toBe([]);
});

it('returns registered components in registration order', function (): void {
    $registry = new TopBarRegistry();

    $registry->register('some-module::top-bar.first');
    $registry->register('other-module::top-bar.second');

    expect($registry->all())->toBe([
        'some-module::top-bar.first',
        'other-module::top-bar.second',
    ]);
});
