<?php

use Livewire\Component;

new class () extends Component {
    public array $pageLayout = [];

    public $modelId = null;
};
?>

<x-noerd::page :detailActions="false">
    <x-noerd::detail-actions :layout="$pageLayout" :modelId="$modelId" />

    <div>OPT-OUT-PROBE-BODY</div>
</x-noerd::page>
