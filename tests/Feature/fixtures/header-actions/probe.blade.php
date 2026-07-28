<?php

use Livewire\Component;

new class () extends Component {
    public ?string $model = null;

    public string $component = '';
};
?>

<div>HA-PROBE:{{ $component }}/{{ $model ?? 'no-model' }}</div>
