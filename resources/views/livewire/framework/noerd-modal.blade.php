<?php

use Livewire\Volt\Component;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

new class extends Component {
    // Sizes
    private const SIZES = [
        // 'product-group-select-modal' => ProductGroupSelectModal::SIZE,
        // 'menu::livewire.deliverytime-component' => DeliverytimeComponent::SIZE,
        // 'menu::livewire.deliverarea-component' => 'sm',
        // 'additional-field-component' => 'sm',
        // 'new-client-component' => 'sm',
        'module' => 'sm',
    ];

    //  #[Url(keep: false, except: '')]
    //  public string $list = '';

    public array $modals = [];

    // public function mount(): void
    // {
    //     $components = explode(',', $this->list);
    //     foreach ($components as $component) {
    //         // Get array key by value
    //         $key = array_search($component, self::ROUTE_MAPPINGS);
    //        if ($key) {
    //            $this->bootModal($key);
    //        }
    //    }
    // }

    #[On('noerdModal')]
    public function bootModal(
        string  $component,
        ?string $source = null,
        array   $arguments = [],
    ): void
    {
        // Nur fortfahhren, wenn noch kein Modal mit dem componentName geöffnet ist
        //if (isset($this->modals[$component]) && $this->modals[$component]['show'] === true) {
        //    return;
        //}
        $modal = [];
        $modal['componentName'] = $component;
        $modal['arguments'] = $arguments;
        $modal['show'] = true;
        $modal['topModal'] = false;
        $modal['source'] = $source;
        $modal['size'] = self::SIZES[$component] ?? 'lg';
        $key = $component;
        foreach ($arguments as $argument) {
            if (is_string($argument) || is_numeric($argument)) {
                $key .= '-' . $argument;
            }
        }
        $modal['key'] = Str::uuid()->toString(); // md5($key);

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

    public function downModal(string $componentName, ?string $source): void // by ESC f.e.
    {
        $modals = $this->modals;
        foreach ($modals as $modal) {
            if ($modal['componentName'] === $componentName && $modal['show'] === true) {
                unset($this->modals[$modal['key']]);
            }
        }

        $this->dispatch('reloadTable-' . $source); // Reload the table, if it is a table component
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

} ?>

<div x-data="{selectedRow: 0, isDragging: false, isLoading: false}">
    @isset($modals)
        @foreach($modals as $key => $modal)

            @teleport('noerdmodal')
            <div x-data="{ show: true }" wire:key="{{$modal['key']}}"
                 @if($modal['show'] && $modal['topModal'])
                     x-init="$store.app.modalOpen = true"
                 @close-modal-{{$modal['componentName']}}.prevent.stop="$wire.downModal('{{$modal['componentName']}}', '{{$modal['source']}}')"
                 @keydown.escape.window.prevent.stop="$wire.downModal('{{$modal['componentName']}}', '{{$modal['source']}}')"
                @endif
            >
                <div x-show="show">
                    <x-noerd::modal>
                        <x-noerd::modal.panel :size="$modal['size']" :ml="$modal['arguments']['ml'] ?? ''"
                                              :iteration="$modal['iteration']"
                                              :source="$modal['source']"
                                              :modal="$modal['componentName']">
                            <div>
                                @livewire($modal['componentName'], $modal['arguments'], key($key))
                            </div>
                        </x-noerd::modal.panel>
                    </x-noerd::modal>
                </div>
            </div>
            @endteleport

        @endforeach
    @endisset
</div>

