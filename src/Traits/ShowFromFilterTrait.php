<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Carbon\Carbon;
use Exception;

/**
 * The date columns the ShowFrom/ShowUntil filters compare against are
 * configured on the component as `protected string $showFromDateColumn` /
 * `protected string $showUntilDateColumn` (both default to `created_at`).
 * The trait deliberately declares NEITHER property: PHP fatals when a class
 * redeclares a trait property with a different default, so the hooks read
 * them through `??`. Override the hook method itself only when the column is
 * dynamic. Keep the properties protected — a public one would be part of the
 * Livewire client payload and end up as a raw column name in the query.
 */
trait ShowFromFilterTrait
{
    protected function getShowFromDateColumn(): string
    {
        return $this->showFromDateColumn ?? 'created_at';
    }

    protected function getShowUntilDateColumn(): string
    {
        return $this->showUntilDateColumn ?? 'created_at';
    }

    protected function resolveShowDate(string $value): ?string
    {
        return match ($value) {
            'today' => Carbon::today()->toDateString(),
            'this_week' => Carbon::today()->startOfWeek()->toDateString(),
            'this_quarter' => Carbon::today()->firstOfQuarter()->toDateString(),
            'last_quarter' => Carbon::today()->subQuarter()->firstOfQuarter()->toDateString(),
            'this_month' => Carbon::today()->startOfMonth()->toDateString(),
            'last_month' => Carbon::today()->subMonth()->startOfMonth()->toDateString(),
            'this_year' => Carbon::today()->startOfYear()->toDateString(),
            default => $this->resolveCustomDate($value),
        };
    }

    protected function resolveCustomDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Exception) {
            return null;
        }
    }

    protected function getShowFromListFilter(): array
    {
        return [
            'label' => __('Show From'),
            'column' => 'show_from',
            'type' => 'ShowFrom',
            'options' => $this->getDateFilterOptions(),
        ];
    }

    protected function getShowUntilListFilter(): array
    {
        return [
            'label' => __('Show Until'),
            'column' => 'show_until',
            'type' => 'ShowUntil',
            'options' => $this->getDateFilterOptions(),
        ];
    }

    protected function getDateFilterOptions(): array
    {
        return [
            '' => '',
            'today' => __('Today'),
            'this_week' => __('This Week'),
            'this_month' => __('This Month'),
            'last_month' => __('Last Month'),
            'this_quarter' => __('This Quarter'),
            'last_quarter' => __('Last Quarter'),
            'this_year' => __('This Year'),
        ];
    }
}
