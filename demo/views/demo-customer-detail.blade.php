<?php

use App\Models\DemoCategory;
use App\Models\DemoCustomer;
use App\Models\DemoTag;
use Livewire\Component;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public $detailModel = DemoCustomer::class;

    public ?string $detailPrimary = 'demoCustomerId';

    /** The tags picked in the belongsToMany field. */
    public array $tagIds = [];

    public function mount(): void
    {
        $this->initDetail();

        if ($this->modelId) {
            $this->tagIds = DemoCustomer::find($this->modelId)?->tags()->pluck('demo_tags.id')->all() ?? [];
        }
    }

    public function categoryOptions(): array
    {
        return ['' => '-'] + DemoCategory::orderBy('name')->pluck('name', 'id')->all();
    }

    public function tagOptions(): array
    {
        return DemoTag::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * The trait's default store() persists the form; the many-to-many tags are
     * the one thing the YAML cannot express, so they are synced here.
     */
    public function store(): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        $this->validateFromLayout();

        $this->detailData['demo_category_id'] = ($this->detailData['demo_category_id'] ?? null) ?: null;

        $demoCustomer = DemoCustomer::updateOrCreate(
            ['id' => $this->modelId],
            $this->writableDetailData(DemoCustomer::class),
        );
        $demoCustomer->tags()->sync($this->tagIds);

        $this->finishStore($demoCustomer);
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Demo Customer') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
