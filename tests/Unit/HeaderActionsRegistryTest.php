<?php

use Noerd\Services\HeaderActionsRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('starts empty so the core renders no module actions of its own', function (): void {
    $registry = new HeaderActionsRegistry();

    expect($registry->listActions())->toBe([])
        ->and($registry->detailActions())->toBe([]);
});

it('keeps list and detail registrations in separate slots', function (): void {
    $registry = new HeaderActionsRegistry();

    $registry->registerListAction('some-module::list-action');
    $registry->registerDetailAction('other-module::detail-action');

    expect($registry->listActions())->toBe(['some-module::list-action'])
        ->and($registry->detailActions())->toBe(['other-module::detail-action']);
});

it('returns registered actions in registration order', function (): void {
    $registry = new HeaderActionsRegistry();

    $registry->registerListAction('some-module::first');
    $registry->registerListAction('other-module::second');

    expect($registry->listActions())->toBe([
        'some-module::first',
        'other-module::second',
    ]);
});

it('lists a universal action in both slots when registered twice', function (): void {
    $registry = new HeaderActionsRegistry();

    $registry->registerListAction('some-module::universal-action');
    $registry->registerDetailAction('some-module::universal-action');

    expect($registry->listActions())->toBe(['some-module::universal-action'])
        ->and($registry->detailActions())->toBe(['some-module::universal-action']);
});
