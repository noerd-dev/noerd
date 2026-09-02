<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;

/**
 * Test-only list host (PageChromeLayoutTest, PageDisableModalTest): a minimal
 * NoerdList over an in-memory row set with a synthetic list config, so the page
 * chrome tests never depend on a shipped list YAML. A list host's body carries
 * no chrome padding — the list brings its own spacing.
 *
 * `withTabs` and `disableModal` are set as mount properties by the tests.
 */
new class extends Component {
    use NoerdList;

    public bool $withTabs = false;

    /**
     * @return array<string, mixed>
     */
    public function listData(): array
    {
        return $this->buildList([['id' => 1, 'name' => 'Alice']], [
            'title' => 'Things',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
            ],
        ]);
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title :listControls="false">Things</x-noerd::modal-title>
    </x-slot:header>

    @if ($withTabs)
        <x-noerd::tabs>
            <x-noerd::tab :tabNumber="1">First</x-noerd::tab>
        </x-noerd::tabs>
    @endif

    <x-noerd::list hideHead />
</x-noerd::page>
