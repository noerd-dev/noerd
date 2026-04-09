<?php

namespace Noerd\Traits;

trait ShowFromFilterTrait
{
    protected function getShowFromListFilter(): array
    {
        return [
            'label' => __('noerd_label_show_from'),
            'column' => 'show_from',
            'type' => 'ShowFrom',
            'options' => $this->getDateFilterOptions(),
        ];
    }

    protected function getShowUntilListFilter(): array
    {
        return [
            'label' => __('noerd_label_show_until'),
            'column' => 'show_until',
            'type' => 'ShowUntil',
            'options' => [
                'today' => __('noerd_show_from_today'),
            ],
        ];
    }

    protected function getShowFromDateColumn(): string
    {
        return 'created_at';
    }

    protected function getShowUntilDateColumn(): string
    {
        return $this->getShowFromDateColumn();
    }

    protected function resolveShowDate(string $key): ?string
    {
        return match ($key) {
            'today' => now()->startOfDay()->toDateString(),
            'this_week' => now()->startOfWeek()->toDateString(),
            'this_quarter' => now()->firstOfQuarter()->toDateString(),
            'last_quarter' => now()->subQuarterNoOverflow()->firstOfQuarter()->toDateString(),
            'this_month' => now()->startOfMonth()->toDateString(),
            'last_month' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'this_year' => now()->startOfYear()->toDateString(),
            'one_week' => now()->subWeek()->startOfDay()->toDateString(),
            'one_month' => now()->subMonth()->startOfDay()->toDateString(),
            'one_year' => now()->subYear()->startOfDay()->toDateString(),
            default => preg_match('/^\d{4}-\d{2}-\d{2}$/', $key) ? $key : null,
        };
    }

    /** @deprecated Use resolveShowDate() instead */
    protected function resolveShowFromDate(string $key): ?string
    {
        return $this->resolveShowDate($key);
    }

    protected function getDateFilterOptions(): array
    {
        return [
            'today' => __('noerd_show_from_today'),
            'this_week' => __('noerd_show_from_this_week'),
            'this_quarter' => __('noerd_show_from_this_quarter'),
            'last_quarter' => __('noerd_show_from_last_quarter'),
            'this_month' => __('noerd_show_from_this_month'),
            'last_month' => __('noerd_show_from_last_month'),
            'this_year' => __('noerd_show_from_this_year'),
            'one_week' => __('noerd_show_from_one_week'),
            'one_month' => __('noerd_show_from_one_month'),
            'one_year' => __('noerd_show_from_one_year'),
        ];
    }
}
