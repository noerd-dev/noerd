<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Services\RelationBoxRegistry;

new class extends Component {
    public string $modelClass = '';

    public mixed $modelId = null;

    /** @var array<int, array<string, mixed>> */
    public array $relations = [];

    /** @var array<int, array{label: string, heroicon: string, component: string, route: string, count: int, arguments: array<string, mixed>}> */
    public array $resolvedRelations = [];

    public function mount(): void
    {
        $this->buildRelations();
    }

    #[On('closeTopModal')]
    public function refreshCounts(): void
    {
        $this->buildRelations();
    }

    private function buildRelations(): void
    {
        $this->resolvedRelations = [];

        if (! $this->modelId || ! class_exists($this->modelClass) || ! is_subclass_of($this->modelClass, Model::class)) {
            return;
        }

        $model = ($this->modelClass)::find($this->modelId);

        if (! $model) {
            return;
        }

        // Page YAML tiles first (authored order — the page owner controls its
        // layout), tiles contributed via the RelationBoxRegistry append after,
        // sorted by the registry. Their closures (count/visible) are resolved
        // here to scalars and never become Livewire state.
        $tiles = [...$this->relations, ...app(RelationBoxRegistry::class)->for($this->modelClass)];

        foreach ($tiles as $tile) {
            $resolved = $this->resolveTile($tile, $model);

            if ($resolved !== null) {
                $this->resolvedRelations[] = $resolved;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $tile
     * @return array{label: string, heroicon: string, component: string, route: string, count: int, arguments: array<string, mixed>}|null
     */
    private function resolveTile(array $tile, Model $model): ?array
    {
        $visible = $tile['visible'] ?? null;

        if ($visible instanceof Closure && ! $visible($model)) {
            return null;
        }

        $relationName = $tile['relation'] ?? null;
        $countResolver = $tile['count'] ?? null;

        $count = match (true) {
            $countResolver instanceof Closure => (int) $countResolver($model),
            $relationName && method_exists($model, $relationName) => $model->{$relationName}()->count(),
            default => 0,
        };

        $route = $tile['route'] ?? null;

        return [
            'label' => $tile['label'] ?? '',
            'heroicon' => $tile['heroicon'] ?? 'rectangle-stack',
            'component' => $tile['component'] ?? '',
            'route' => $route && \Illuminate\Support\Facades\Route::has($route) ? $route : '',
            'count' => $count,
            'arguments' => $this->resolveArguments($tile['arguments'] ?? []),
        ];
    }

    /**
     * Resolve YAML argument values. Supports the '$modelId' token and static values.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function resolveArguments(array $arguments): array
    {
        $resolved = [];

        foreach ($arguments as $key => $value) {
            $resolved[$key] = $value === '$modelId' ? $this->modelId : $value;
        }

        return $resolved;
    }
}; ?>

<div class="grid grid-cols-6 gap-4 pb-6">
    @foreach($resolvedRelations as $relation)
        {{-- A tile opens the related list NARROWED by the current record, which a
             plain list route cannot express — so the route only resolves the
             component, the browser URL stays put (rewriteUrl: false). --}}
        <a href="#/"
           wire:key="relation-{{ $loop->index }}"
           @if ($relation['route'])
               @click.prevent="$modalRoute({{ \Illuminate\Support\Js::from($relation['route']) }}, {{ \Illuminate\Support\Js::from($relation['arguments']) }}, null, null, null, {rewriteUrl: false, fallbackComponent: {{ \Illuminate\Support\Js::from($relation['component'] ?: null) }}})"
           @else
               @click.prevent="$modal({{ \Illuminate\Support\Js::from($relation['component']) }}, {{ \Illuminate\Support\Js::from($relation['arguments']) }})"
           @endif
           class="bg-white border border-gray-300 hover:bg-gray-50 flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer">
            <x-icon name="{{ $relation['heroicon'] }}" class="w-5 h-5 shrink-0 text-gray-800"/>
            <span class="text-sm text-gray-600 truncate">{{ __($relation['label']) }} ({{ $relation['count'] }})</span>
        </a>
    @endforeach
</div>
