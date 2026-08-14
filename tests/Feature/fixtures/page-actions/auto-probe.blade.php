<?php

use Livewire\Component;

new class () extends Component {
    public array $pageLayout = [];

    public $modelId = null;

    public bool $quickCreate = false;

    public bool $embedded = false;
};
?>

<x-noerd::page>
    <div>AUTO-PROBE-BODY</div>
</x-noerd::page>
