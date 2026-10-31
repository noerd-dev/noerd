<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\ComponentHook;
use Livewire\Exceptions\MethodNotFoundException;
use Noerd\Traits\NoerdComponentShared;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;
use Noerd\Traits\NoerdPositionRow;

/**
 * Refuses browser calls to the public trait methods that exist for Blade,
 * hooks and tests only. Livewire offers every public method as an action, and
 * an action's return value travels to the browser: `listData()` would hand out
 * the whole paginator with every visible attribute, and the trait-suffixed
 * lifecycle hooks (`renderingNoerdList`, `queryStringNoerdPage`, …) are not
 * covered by Livewire's own lifecycle guard. The PHP visibility stays public —
 * module views and tests call several of them.
 */
final class ServerOnlyMethodsHook extends ComponentHook
{
    /**
     * @var array<class-string, list<string>>
     */
    private const METHODS = [
        NoerdList::class => [
            'listData',
            'builtListConfig',
            'headerControls',
            'hasCollapsibleControls',
            'hasHeaderControls',
            'visibleRowIds',
            'normalizeRecordId',
            'isSortableColumn',
            'renderingNoerdList',
        ],
        NoerdPage::class => [
            'mountNoerdPage',
            'queryStringNoerdPage',
            'renderingNoerdPage',
            'renderedNoerdPage',
            'embeddedDetailComponent',
            'detailTheme',
            'positionColumns',
        ],
        NoerdDetail::class => [
            'renderingNoerdDetail',
            'validateFromLayout',
        ],
        NoerdPositionRow::class => [
            'initPositionRow',
            'editablePositionValues',
            'positionColumnCount',
        ],
        NoerdComponentShared::class => [
            'getComponentName',
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
