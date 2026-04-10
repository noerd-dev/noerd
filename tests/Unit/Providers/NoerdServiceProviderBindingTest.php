<?php

use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Repositories\YamlSetupCollectionDefinitionRepository;

uses(Tests\TestCase::class);

it('resolves YamlSetupCollectionDefinitionRepository when mode is yaml', function (): void {
    config(['noerd.collections.mode' => 'yaml']);
    app()->forgetInstance(SetupCollectionDefinitionRepositoryContract::class);

    $repository = app(SetupCollectionDefinitionRepositoryContract::class);

    expect($repository)->toBeInstanceOf(YamlSetupCollectionDefinitionRepository::class);
    expect($repository->isWritable())->toBeFalse();
});

it('resolves DatabaseSetupCollectionDefinitionRepository when mode is database', function (): void {
    config(['noerd.collections.mode' => 'database']);
    app()->forgetInstance(SetupCollectionDefinitionRepositoryContract::class);

    $repository = app(SetupCollectionDefinitionRepositoryContract::class);

    expect($repository)->toBeInstanceOf(DatabaseSetupCollectionDefinitionRepository::class);
    expect($repository->isWritable())->toBeTrue();
});

it('falls back to yaml mode when the config value is unknown', function (): void {
    config(['noerd.collections.mode' => 'something-invalid']);
    app()->forgetInstance(SetupCollectionDefinitionRepositoryContract::class);

    $repository = app(SetupCollectionDefinitionRepositoryContract::class);

    expect($repository)->toBeInstanceOf(YamlSetupCollectionDefinitionRepository::class);
});
