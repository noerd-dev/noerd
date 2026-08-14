<?php

use Livewire\Component;

new class () extends Component {
    public array $pageLayout = [];

    public $modelId = null;

    public function detailActionUrls(): array
    {
        return ['probeUrl' => 'https://example.test/probe'];
    }
};
?>

<x-noerd::page>
    <div>URL-PROBE-BODY</div>
</x-noerd::page>
