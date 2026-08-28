<?php

use Livewire\Component;
use Noerd\Support\ComponentAccessGuard;

new class extends Component
{
    public string $componentName = '';

    public array $arguments = [];

    public function mount(string $componentName): void
    {
        abort_unless(str_contains($componentName, '::'), 404);

        // The component name comes from the URL and is mounted outside its own
        // route, so the target's route middleware never runs — re-assert the
        // admin guard here to keep admin screens unreachable this way.
        ComponentAccessGuard::authorize($componentName);

        $this->componentName = $componentName;
        $this->arguments = request()->query();
    }
}; ?>

<div>
    @livewire($componentName, $arguments, key('generic-component-page-'.md5($componentName.json_encode($arguments))))
</div>
