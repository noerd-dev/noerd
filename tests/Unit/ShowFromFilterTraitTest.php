<?php

declare(strict_types=1);

use Carbon\Carbon;
use Noerd\Tests\TestCase;
use Noerd\Traits\ShowFromFilterTrait;

uses(TestCase::class);

it('resolves the relative date filter keys from the start of their period', function (): void {
    Carbon::setTestNow('2026-09-02'); // a Wednesday

    $component = new class {
        use ShowFromFilterTrait;

        public function resolve(string $value): ?string
        {
            return $this->resolveShowDate($value);
        }
    };

    expect($component->resolve('today'))->toBe('2026-09-02')
        ->and($component->resolve('this_week'))->toBe('2026-08-31')
        ->and($component->resolve('this_month'))->toBe('2026-09-01')
        ->and($component->resolve('this_quarter'))->toBe('2026-07-01')
        ->and($component->resolve('this_year'))->toBe('2026-01-01');

    Carbon::setTestNow();
});
