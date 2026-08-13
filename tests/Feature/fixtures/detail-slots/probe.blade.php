<?php

use Livewire\Component;

new class () extends Component {
    public ?int $modelId = null;

    public string $hostComponent = '';
};
?>

<div>DS-PROBE:{{ $hostComponent }}/{{ $modelId ?? 'no-model' }}</div>
