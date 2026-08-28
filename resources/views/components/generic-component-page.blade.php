<?php

use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Support\ComponentAccessGuard;

new class extends Component
{
    #[Locked]
    public string $componentName = '';

    #[Locked]
    public array $arguments = [];

    public function mount(string $componentName): void
    {
        abort_unless(str_contains($componentName, '::'), 404);

        // The component name comes from the URL and is mounted outside its own
        // route, so the target's route middleware never runs — re-assert the
        // admin guard here to keep admin screens unreachable this way.
        ComponentAccessGuard::authorize($componentName);

        $this->componentName = $componentName;
        $this->arguments = $this->safeArguments(request()->query());
    }

    /**
     * Mount arguments are assigned to matching public properties — including
     * #[Locked] ones, which only veto the UPDATE path. Passing the raw query
     * string therefore let a crafted URL repoint a component's identity before
     * any guard ran (e.g. ?listModel=… aims the generic list query at an
     * unscoped model, ?objectPermissionModel=… neutralises the object gates).
     * Only addressing parameters are forwarded.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function safeArguments(array $query): array
    {
        $allowed = ['modelId', 'relations', 'quickCreate', 'parentId', 'context', 'key', 'tab'];

        return array_filter(
            array_intersect_key($query, array_flip($allowed)),
            fn(mixed $value): bool => is_scalar($value) || is_array($value),
        );
    }
}; ?>

<div>
    @livewire($componentName, $arguments, key('generic-component-page-'.md5($componentName.json_encode($arguments))))
</div>
