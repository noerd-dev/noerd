<?php

use Livewire\Component;

new class () extends Component {
    public ?int $modelId = null;

    public string $hostComponent = '';
};
?>

<div>DS-PROBE-B:{{ $hostComponent }}</div>
