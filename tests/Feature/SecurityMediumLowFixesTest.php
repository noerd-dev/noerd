<?php

declare(strict_types=1);

use Noerd\Helpers\StaticConfigHelper;
use Noerd\Support\WriteGuardHook;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;

uses(TestCase::class);

// ---------------------------------------------------------------------------
// M1 — CSV formula injection is neutralized
// ---------------------------------------------------------------------------

it('neutralizes CSV formula-triggering values in the export', function (): void {
    $list = new class {
        use NoerdList;

        public function format(mixed $value, array $column): string
        {
            return $this->formatCsvValue($value, $column);
        }
    };

    foreach (['=HYPERLINK("x")', '+1', '-cmd', '@SUM(A1)', "\tx", "\rx"] as $dangerous) {
        expect($list->format($dangerous, ['type' => 'text']))->toStartWith("'");
    }

    // Ordinary text is untouched.
    expect($list->format('Acme GmbH', ['type' => 'text']))->toBe('Acme GmbH');
    // Numbers keep their formatting (a negative number is not a formula here).
    config(['noerd.format.decimal_separator' => ',', 'noerd.format.thousands_separator' => '.']);
    expect($list->format(-5, ['type' => 'number']))->toBe('-5,00');
});

// ---------------------------------------------------------------------------
// L1 — perPage is clamped
// ---------------------------------------------------------------------------

it('clamps the client-controlled page size', function (): void {
    $list = new class {
        use NoerdList;

        public function clamp(int $perPage): int
        {
            return $this->clampPerPage($perPage);
        }
    };

    expect($list->clamp(999999))->toBe(200);
    expect($list->clamp(0))->toBe(1);
    expect($list->clamp(50))->toBe(50);
});

// ---------------------------------------------------------------------------
// M5 — config path resolution rejects traversal
// ---------------------------------------------------------------------------

it('rejects a traversal app segment when resolving a config path', function (): void {
    expect(StaticConfigHelper::resolveConfigPath('../../etc', 'list', 'x'))->toBeNull();
    expect(StaticConfigHelper::resolveConfigPath('foo/bar', 'list', 'x'))->toBeNull();
    expect(StaticConfigHelper::resolveConfigPath('..', 'detail', 'x'))->toBeNull();
});

// ---------------------------------------------------------------------------
// M4 — WriteGuardHook enforces the object permission on custom store()/delete()
// ---------------------------------------------------------------------------

it('WriteGuardHook skips store/delete when the object permission denies it', function (): void {
    $denied = new class {
        use NoerdPage;

        public function canWriteObject(): bool
        {
            return false;
        }

        // store() on a component without a $modelId is a CREATE — the hook
        // derives the ability via canSaveObject(), so both must deny here.
        public function canCreateObject(): bool
        {
            return false;
        }

        public function canDeleteObject(): bool
        {
            return false;
        }
    };

    $hook = new WriteGuardHook();
    $hook->setComponent($denied);

    $storeSkipped = false;
    $hook->call('store', [], function () use (&$storeSkipped): void {
        $storeSkipped = true;
    }, [], null);
    expect($storeSkipped)->toBeTrue();

    $deleteSkipped = false;
    $hook->call('delete', [], function () use (&$deleteSkipped): void {
        $deleteSkipped = true;
    }, [], null);
    expect($deleteSkipped)->toBeTrue();
});

it('WriteGuardHook lets the action run when writing is allowed', function (): void {
    $allowed = new class {
        use NoerdPage;

        public function canWriteObject(): bool
        {
            return true;
        }
    };

    $hook = new WriteGuardHook();
    $hook->setComponent($allowed);

    $skipped = false;
    $hook->call('store', [], function () use (&$skipped): void {
        $skipped = true;
    }, [], null);
    expect($skipped)->toBeFalse();

    // An unrelated method is never touched.
    $hook->call('someOtherAction', [], function () use (&$skipped): void {
        $skipped = true;
    }, [], null);
    expect($skipped)->toBeFalse();
});
