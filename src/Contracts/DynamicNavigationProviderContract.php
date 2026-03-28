<?php

namespace Noerd\Contracts;

interface DynamicNavigationProviderContract
{
    /**
     * Return the dynamic navigation type key this provider handles.
     */
    public function type(): string;

    /**
     * Build and return the navigation items array.
     *
     * @return array<int, array{title: string, link: string, icon?: string, heroicon?: string}>
     */
    public function items(): array;
}
