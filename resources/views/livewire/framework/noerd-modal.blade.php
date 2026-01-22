<?php

use Livewire\Volt\Component;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

new class extends Component {

    public array $modals = [];

    #[On('noerdModal')]
    public function bootModal(
        string  $modalComponent,
        ?string $source = null,
        array   $arguments = [],
    ): void
    {
        $modal = [];
        $modal['componentName'] = $modalComponent;
        $modal['arguments'] = $arguments;
        $modal['show'] = true;
        $modal['topModal'] = false;
        $modal['source'] = $source;
        $modal['key'] = md5(serialize($arguments));

        // DEBUG
        \Log::debug('[noerd-modal Debug] bootModal', [
            'component' => $modalComponent,
            'arguments' => $arguments,
            'serialized' => serialize($arguments),
            'key' => $modal['key'],
            'existing_modal_keys' => array_keys($this->modals),
        ]);

        $iteration = 1;
        foreach ($this->modals as $checkModal) {
            if ($checkModal['show'] === true) {
                $iteration++;
            }
        }

        $modal['iteration'] = $iteration;
        $this->modals[$modal['key']] = $modal;

        $this->markTopModal();
    }

    public function downModal(string $componentName, ?string $source, ?string $modalKey): void // by ESC f.e.
    {
        $this->dispatch('close-modal-' . $componentName, $source, $modalKey);
        $this->dispatch('refreshList-' . $source); // Reload the table, if it is a table component
    }

    #[On('downModal2')]
    public function downModal2(string $componentName, ?string $source, ?string $modalKey): void // by ESC f.e.
    {
        $modals = $this->modals;
        foreach ($modals as $modal) {
            if ($modal['componentName'] === $componentName && $modal['key'] === $modalKey) {
                unset($this->modals[$modal['key']]);
            }
        }

        $this->dispatch('refreshList-' . $source); // Reload the table, if it is a table component
        $this->markTopModal();

        // Check if no modals are open and reset modalOpen flag
        $hasOpenModal = false;
        foreach ($this->modals as $modal) {
            if ($modal['show'] === true) {
                $hasOpenModal = true;
                break;
            }
        }

        if (!$hasOpenModal) {
            // This re-enables keyboard control in tables.
            $this->dispatch('modal-closed-global');
        }
    }

    private function markTopModal(): void
    {
        foreach ($this->modals as $key => $modal) {
            $this->modals[$key]['topModal'] = false;
        }
        $lastKey = null;
        if (count($this->modals) > 0) {
            foreach ($this->modals as $key => $modal) {
                if ($modal['show'] === true) {
                    $lastKey = $key;
                }
            }

            if ($lastKey) {
                $this->modals[$lastKey]['topModal'] = true;
            }
        }
    }

    public function toggleFullscreen(): void
    {
        if (session('modal_fullscreen')) {
            session()->forget('modal_fullscreen');
        } else {
            session(['modal_fullscreen' => true]);
        }
    }

} ?>

<div x-data="{selectedRow: 0, isDragging: false, isLoading: false}">
    @isset($modals)
        @foreach($modals as $key => $modal)
            <div x-data="{ show: true }" wire:key="modal-wrapper-{{$modal['key']}}"
                 @if($modal['show'] && $modal['topModal'])
                     x-init="$store.app.modalOpen = true"
                 @close-modal-{{$modal['componentName']}}.prevent.stop="$wire.downModal('{{$modal['componentName']}}', '{{$modal['source']}}', '{{$modal['key']}}')"
                 @keydown.escape.window.prevent.stop="$wire.downModal('{{$modal['componentName']}}', '{{$modal['source']}}', '{{$modal['key']}}')"
                @endif
            >
                <div x-show="show">
                    <x-noerd::modal>
                        <x-noerd::modal.panel :ml="$modal['arguments']['ml'] ?? ''"
                                              :iteration="$modal['iteration']"
                                              :source="$modal['source']"
                                              :modalKey="$modal['key']"
                                              :modal="$modal['componentName']">
                            <div wire:key="modal-content-{{$modal['key']}}">
                                @livewire($modal['componentName'], $modal['arguments'], key($modal['key']))
                            </div>
                        </x-noerd::modal.panel>
                    </x-noerd::modal>
                </div>
            </div>
        @endforeach
    @endisset
</div>

