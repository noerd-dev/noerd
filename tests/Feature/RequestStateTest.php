<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\Tenant;
use Noerd\Support\RequestState;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The request memos of the static helpers live in the scoped RequestState:
 | a long-running worker drops them before every job and every Octane request
 | (forgetScopedInstances()), so no job reads the state of the previous one.
 */

it('drops the request memos when the container forgets its scoped instances', function (): void {
    $tenant = Tenant::factory()->create();
    TenantHelper::setSelectedTenantId($tenant->id);

    expect(NoerdSettings::forTenant($tenant->id))->toBeNull();

    // A settings row written by another process is invisible to the memo …
    NoerdSettings::query()->insert(['tenant_id' => $tenant->id, 'currency' => 'CHF']);
    expect(NoerdSettings::forTenant($tenant->id))->toBeNull();

    // … until the worker moves on to the next job.
    app()->forgetScopedInstances();

    expect(NoerdSettings::forTenant($tenant->id)?->currency)->toBe('CHF');
});

it('keeps one bucket per name and forgets them separately', function (): void {
    $first = &RequestState::bucket('zz.first');
    $first['a'] = 1;
    $second = &RequestState::bucket('zz.second');
    $second['b'] = 2;

    RequestState::forget('zz.first');

    expect(RequestState::bucket('zz.first'))->toBe([])
        ->and(RequestState::bucket('zz.second'))->toBe(['b' => 2]);
});

it('ships every config key in the published stub', function (): void {
    $keys = function (array $config, string $prefix = '') use (&$keys): array {
        $flat = [];
        foreach ($config as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $flat[] = $path;
            if (is_array($value) && ! array_is_list($value)) {
                $flat = [...$flat, ...$keys($value, $path)];
            }
        }

        return $flat;
    };

    $package = $keys(require dirname(__DIR__, 2) . '/config/noerd.php');
    $stub = $keys(require dirname(__DIR__, 2) . '/stubs/noerd.php.stub');

    expect(array_values(array_diff($package, $stub)))->toBe([]);
});
