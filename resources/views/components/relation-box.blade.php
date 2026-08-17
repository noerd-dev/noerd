<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;

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

        foreach ($this->relations as $relation) {
            $relationName = $relation['relation'] ?? null;
            $count = $relationName && method_exists($model, $relationName)
                ? $model->{$relationName}()->count()
                : 0;

            $route = $relation['route'] ?? null;

            $this->resolvedRelations[] = [
                'label' => $relation['label'] ?? '',
                'heroicon' => $relation['heroicon'] ?? 'rectangle-stack',
                'component' => $relation['component'] ?? '',
                'route' => $route && \Illuminate\Support\Facades\Route::has($route) ? $route : '',
                'count' => $count,
                'arguments' => $this->resolveArguments($relation['arguments'] ?? []),
            ];
        }
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
