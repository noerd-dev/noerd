<?php

use Noerd\Services\RelationBoxRegistry;

class RbrBaseModel {}
class RbrChildModel extends RbrBaseModel {}

it('starts empty so a model gets no contributed tiles by default', function (): void {
    $registry = new RelationBoxRegistry();

    expect($registry->for(RbrBaseModel::class))->toBe([]);
});

it('orders tiles by sort ascending regardless of registration order', function (): void {
    $registry = new RelationBoxRegistry();

    $registry->register(RbrBaseModel::class, ['label' => 'Second'], sort: 10);
    $registry->register(RbrBaseModel::class, ['label' => 'First'], sort: 5);

    expect($registry->for(RbrBaseModel::class))->toBe([
        ['label' => 'First'],
        ['label' => 'Second'],
    ]);
});

it('keeps registration order for equal sort values', function (): void {
    $registry = new RelationBoxRegistry();

    $registry->register(RbrBaseModel::class, ['label' => 'First']);
    $registry->register(RbrBaseModel::class, ['label' => 'Second']);

    expect($registry->for(RbrBaseModel::class))->toBe([
        ['label' => 'First'],
        ['label' => 'Second'],
    ]);
});

it('keeps unrelated model classes independent of each other', function (): void {
    $registry = new RelationBoxRegistry();

    $registry->register(RbrBaseModel::class, ['label' => 'Base Tile']);
    $registry->register(RbrChildModel::class, ['label' => 'Child Tile']);

    expect($registry->for(RbrBaseModel::class))->toBe([['label' => 'Base Tile']]);
});

it('lets a subclass inherit tiles registered for its parent class', function (): void {
    $registry = new RelationBoxRegistry();

    $registry->register(RbrBaseModel::class, ['label' => 'Base Tile']);

    expect($registry->for(RbrChildModel::class))->toBe([['label' => 'Base Tile']])
        ->and($registry->for(RbrBaseModel::class))->toBe([['label' => 'Base Tile']]);
});
