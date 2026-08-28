<?php

use Noerd\Services\DetailSlotsRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('orders slot components by sort ascending regardless of registration order', function (): void {
    $registry = new DetailSlotsRegistry();

    $registry->register('user-below-form', 'some-module::second', sort: 10);
    $registry->register('user-below-form', 'other-module::first', sort: 5);

    expect($registry->for('user-below-form'))->toBe([
        'other-module::first',
        'some-module::second',
    ]);
});

it('keeps registration order for equal sort values', function (): void {
    $registry = new DetailSlotsRegistry();

    $registry->register('user-below-form', 'some-module::first');
    $registry->register('user-below-form', 'other-module::second');

    expect($registry->for('user-below-form'))->toBe([
        'some-module::first',
        'other-module::second',
    ]);
});

it('keeps slots independent of each other', function (): void {
    $registry = new DetailSlotsRegistry();

    $registry->register('user-below-form', 'some-module::user-extension');
    $registry->register('customer-below-form', 'some-module::customer-extension');

    expect($registry->for('user-below-form'))->toBe(['some-module::user-extension'])
        ->and($registry->for('customer-below-form'))->toBe(['some-module::customer-extension']);
});
