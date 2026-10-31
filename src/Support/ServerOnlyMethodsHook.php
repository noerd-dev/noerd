<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\ComponentHook;
use Livewire\Exceptions\MethodNotFoundException;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;

/**
 * Refuses browser calls to the few PUBLIC trait methods that are not actions:
 * `listData()` — public because ~every module declares its override public,
 * and its return value would hand the browser the whole paginator — and the
 * trait-suffixed lifecycle hooks (`renderingNoerdList`, `queryStringNoerdPage`,
 * …), which Livewire calls itself and whose names its own lifecycle guard does
 * not know. Every other helper the views, hooks and tests need is `protected`:
 * Livewire only offers public methods as actions, and a view is rendered inside
 * the component's scope, so Blade reaches a protected method just the same.
 */
final class ServerOnlyMethodsHook extends ComponentHook
{
    /**
     * @var array<class-string, list<string>>
     */
    private const METHODS = [
        NoerdList::class => [
            'listData',
            'renderingNoerdList',
        ],
        NoerdPage::class => [
            'mountNoerdPage',
            'queryStringNoerdPage',
            'renderingNoerdPage',
            'renderedNoerdPage',
        ],
        NoerdDetail::class => [
            'renderingNoerdDetail',
        ],
    ];

    public function call($method, $params, $returnEarly, $metadata, $componentContext): void
    {
        if (! is_string($method)) {
            return;
        }

        $traits = class_uses_recursive($this->component);

        foreach (self::METHODS as $trait => $methods) {
            if (in_array($trait, $traits, true) && in_array($method, $methods, true)) {
                throw new MethodNotFoundException($method);
            }
        }
    }
}
