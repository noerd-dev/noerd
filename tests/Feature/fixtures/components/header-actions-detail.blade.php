<?php

use Livewire\Component;
use Noerd\Models\NoerdUser;
use Noerd\Traits\NoerdDetail;

/**
 * Test-only detail host (HeaderActionsRenderTest): a NoerdDetail with a synthetic
 * two-tab layout and a nested block, rendering the standard chrome. Proves where the
 * registered detail header actions mount without asserting any shipped YAML.
 */
new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'noerdUserId';

    public $detailModel = NoerdUser::class;

    /** Render the component's own `blockActions` slot next to the registry actions. */
    public bool $withSlot = false;

    public function mount(bool $withSlot = false): void
    {
        $this->withSlot = $withSlot;
        $this->initDetail();

        $this->pageLayout = [
            'title' => 'Zz Header Actions Block',
            'tabs' => [
                ['number' => 1, 'label' => 'One'],
                ['number' => 2, 'label' => 'Two'],
            ],
            'fields' => [
                ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
                [
                    'type' => 'block',
                    'title' => 'Zz Nested Block',
                    'colspan' => 12,
                    'fields' => [
                        ['name' => 'detailData.email', 'label' => 'Email', 'type' => 'text', 'colspan' => 6],
                    ],
                ],
                ['name' => 'detailData.phone', 'label' => 'Phone', 'type' => 'text', 'colspan' => 6, 'tab' => 2],
            ],
        ];
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Zz Header Actions Header</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
        @if ($withSlot)
            <x-slot:blockActions>
                <button type="button">ZZ-SLOT-BUTTON</button>
            </x-slot:blockActions>
        @endif
    </x-noerd::tab-content>
</x-noerd::page>
