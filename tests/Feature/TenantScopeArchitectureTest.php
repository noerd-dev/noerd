<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | The core never lifts the tenant filter by hand: a query of another tenant names
 | it with ofTenant($id), a deliberate sweep over every tenant with acrossTenants().
 | Both lift ONLY the tenant scope — withoutGlobalScopes() would also drop soft
 | deletes and the object read guard. The two files below are the ones that
 | implement those rules.
 */
it('lifts the tenant scope only through ofTenant() and acrossTenants()', function (): void {
    $allowed = [
        'src/Traits/BelongsToTenant.php',
        'src/Traits/TenantSingleton.php',
    ];

    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach (['src', 'resources/views'] as $directory) {
        foreach (File::allFiles($root . '/' . $directory) as $file) {
            $relative = mb_ltrim(str_replace($root, '', $file->getPathname()), '/');

            if (in_array($relative, $allowed, true) || $file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/withoutGlobalScopes\(|withoutGlobalScope\(\s*TenantScope::class/', $file->getContents()) === 1) {
                $offenders[] = $relative;
            }
        }
    }

    expect($offenders)->toBe([]);
});
