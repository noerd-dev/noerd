<?php

use Livewire\Volt\Component;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

new class extends Component {
    // Sizes
    private const SIZES = [
        // 'product-group-select-modal' => ProductGroupSelectModal::SIZE,
        // 'menu::livewire.deliverytime-detail' => DeliverytimeComponent::SIZE,
        // 'menu::livewire.deliverarea-detail' => 'sm',
        // 'additional-field-detail' => 'sm',
        // 'new-client-detail' => 'sm',
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
        //$key = $component;
        //foreach ($arguments as $argument) {
        //    if (is_string($argument) || is_numeric($argument)) {
        //        $key .= '-' . $argument;
        //    }
        //}
        $modal['key'] = md5(serialize($arguments));

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
        $this->dispatch('reloadTable-' . $source); // Reload the table, if it is a table component

        /*
         return;
        // TOOD: the downModal2 is currently needed to remove the URL Parameter again
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
            //       $this->dispatch('modal-closed-global');
        }
        */
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

} ?>

<div x-data="{selectedRow: 0, isDragging: false, isLoading: false}">
    @isset($modals)
        @foreach($modals as $key => $modal)

            @teleport('noerdmodal')
            <div x-data="{ show: true }" wire:key="{{$modal['key']}}"
                 @if($modal['show'] && $modal['topModal'])
                     x-init="$store.app.modalOpen = true"
                 @close-modal-{{$modal['componentName']}}.prevent.stop="$wire.downModal('{{$modal['componentName']}}', '{{$modal['source']}}', '{{$modal['key']}}')"
                 @keydown.escape.window.prevent.stop="$wire.downModal('{{$modal['componentName']}}', '{{$modal['source']}}', '{{$modal['key']}}')"
                @endif
            >
                <div x-show="show">
                    <x-noerd::modal>
                        <x-noerd::modal.panel :size="$modal['size']" :ml="$modal['arguments']['ml'] ?? ''"
                                              :iteration="$modal['iteration']"
                                              :source="$modal['source']"
                                              :modalKey="$modal['key']"
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

