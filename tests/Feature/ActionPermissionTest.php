<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Noerd\Enums\Profile;
use Noerd\Helpers\AccessHelper;
use Noerd\Middleware\ActionPermissionMiddleware;
use Noerd\Models\NoerdUser;
use Noerd\Services\ActionPermissionRegistry;
use Noerd\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Named action permissions: the registry catalogs what modules declare, the
 | `noerd.action` gate (or the profile baseline as fallback) decides, and the
 | `action-permission:{key}` middleware guards routes.
 */

it('allows an action with no gate defined for a regular user', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::User));

    expect(AccessHelper::canPerformAction('production_start_run'))->toBeTrue()
        ->and(AccessHelper::canPerformAction(null))->toBeTrue()
        ->and(AccessHelper::canPerformAction(''))->toBeTrue();
});

it('denies an action for a READ_ONLY profile with no gate defined', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::ReadOnly));

    expect(AccessHelper::canPerformAction('production_start_run'))->toBeFalse();
});

it('lets a defined action gate decide alone', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::User));

    Gate::define(
        AccessHelper::ACTION_GATE,
        fn(?NoerdUser $user, string $actionKey): bool => $actionKey === 'production_start_run',
    );

    expect(AccessHelper::canPerformAction('production_start_run'))->toBeTrue()
        ->and(AccessHelper::canPerformAction('production_cancel_run'))->toBeFalse();
});

it('passes the middleware when the action is allowed', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::User));

    $response = (new ActionPermissionMiddleware())->handle(
        Request::create('/zz-action', 'POST'),
        fn() => response('OK'),
        'production_start_run',
    );

    expect($response->getContent())->toBe('OK');
});

it('aborts the middleware with 403 when the action is denied', function (): void {
    $this->actingAs(createNoerdUserWithProfile(Profile::User));

    Gate::define(AccessHelper::ACTION_GATE, fn(?NoerdUser $user, string $actionKey): bool => false);

    try {
        (new ActionPermissionMiddleware())->handle(
            Request::create('/zz-action', 'POST'),
            fn() => response('OK'),
            'production_start_run',
        );
        $this->fail('Expected a 403 abort.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('catalogs registered actions for permission UIs', function (): void {
    $registry = app(ActionPermissionRegistry::class);
    $registry->register('production_start_run', 'Start Production Run');
    $registry->register('crm_merge_accounts', 'Merge Accounts');

    expect(app(ActionPermissionRegistry::class))->toBe($registry)
        ->and($registry->all())->toBe([
            'crm_merge_accounts' => 'Merge Accounts',
            'production_start_run' => 'Start Production Run',
        ])
        ->and($registry->has('production_start_run'))->toBeTrue()
        ->and($registry->has('unknown'))->toBeFalse()
        ->and($registry->label('crm_merge_accounts'))->toBe('Merge Accounts');
});

it('rejects action keys that are not snake_case', function (): void {
    $registry = app(ActionPermissionRegistry::class);

    expect(fn() => $registry->register('production.start-run', 'Dotted'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn() => $registry->register('Production_Run', 'Cased'))
        ->toThrow(InvalidArgumentException::class);
});
