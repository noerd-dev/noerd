<?php

use Livewire\Component;

new class extends Component
{
    public string $componentName = '';

    public array $arguments = [];

    public function mount(string $componentName): void
    {
        abort_unless(str_contains($componentName, '::'), 404);

        $this->componentName = $componentName;
        $this->arguments = request()->query();
    }
}; ?>

<div>
    @livewire($componentName, $arguments, key('generic-component-page-'.md5($componentName.json_encode($arguments))))
</div>
