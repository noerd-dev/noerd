<?php

declare(strict_types=1);

use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Repositories\YamlSetupCollectionDefinitionRepository;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('reads the setup collection definitions from YAML', function (): void {
    expect(app(SetupCollectionDefinitionRepositoryContract::class))
        ->toBeInstanceOf(YamlSetupCollectionDefinitionRepository::class);
});
