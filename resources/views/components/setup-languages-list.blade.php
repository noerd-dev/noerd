<?php

use Livewire\Component;
use Noerd\Models\SetupLanguage;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = SetupLanguage::class;
    public ?string $detailRoute = 'noerd.setup-language.detail';

    public $detailComponent = 'noerd::setup-language-detail';
}; ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
