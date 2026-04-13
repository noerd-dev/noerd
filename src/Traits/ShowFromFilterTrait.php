<?php

namespace Noerd\Traits;

use Carbon\Carbon;
use Exception;

trait ShowFromFilterTrait
{
    protected function getShowFromDateColumn(): string
    {
        return 'created_at';
    }

    protected function getShowUntilDateColumn(): string
    {
        return 'created_at';
    }

    protected function resolveShowDate(string $value): ?string
    {
        return match ($value) {
            'today' => Carbon::today()->toDateString(),
            'this_week' => Carbon::today()->subDays(7)->toDateString(),
            'this_quarter' => Carbon::today()->firstOfQuarter()->toDateString(),
            'last_quarter' => Carbon::today()->subQuarter()->firstOfQuarter()->toDateString(),
            'this_month' => Carbon::today()->startOfMonth()->toDateString(),
            'last_month' => Carbon::today()->subMonth()->startOfMonth()->toDateString(),
            'this_year' => Carbon::today()->startOfYear()->toDateString(),
            'one_week' => Carbon::today()->subWeek()->toDateString(),
            'one_month' => Carbon::today()->subMonth()->toDateString(),
            'one_year' => Carbon::today()->subYear()->toDateString(),
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
            'options' => $this->getDateFilterOptions(),
        ];
    }

    protected function getDateFilterOptions(): array
    {
        return [
            '' => '',
            'today' => __('noerd_show_from_today'),
            'this_week' => __('noerd_show_from_this_week'),
            'this_month' => __('noerd_show_from_this_month'),
            'last_month' => __('noerd_show_from_last_month'),
            'this_quarter' => __('noerd_show_from_this_quarter'),
            'last_quarter' => __('noerd_show_from_last_quarter'),
            'this_year' => __('noerd_show_from_this_year'),
        ];
    }
}
