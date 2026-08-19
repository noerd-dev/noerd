<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * The module-to-app-config mapping is discovered by scanning base_path('app-modules').
 * One Pest process runs package tests (testbench skeleton, tiny app-modules) next to
 * host tests (full app-modules), so the memo must never outlive its base path.
 */
it('rediscovers the module source mapping when the base path changes', function (): void {
    StaticConfigHelper::clearModuleSourceCache();

    $mapping = new ReflectionMethod(StaticConfigHelper::class, 'getModuleSourceMapping');
    $mapping->setAccessible(true);

    $skeletonBasePath = base_path();
    $skeletonMapping = $mapping->invoke(null);

    $otherBasePath = $skeletonBasePath . '/tests-other-root';
    File::ensureDirectoryExists($otherBasePath . '/app-modules/zz-fixture/app-configs/zzapp');
    app()->setBasePath($otherBasePath);

    try {
        expect($mapping->invoke(null))->toBe(['zzapp' => ['zz-fixture']])
            ->and($skeletonMapping)->not->toHaveKey('zzapp');
    } finally {
        app()->setBasePath($skeletonBasePath);
        File::deleteDirectory($otherBasePath);
    }

    expect($mapping->invoke(null))->toBe($skeletonMapping);
});
