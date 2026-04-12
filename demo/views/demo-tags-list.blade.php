<?php

use App\Models\DemoTag;
use Livewire\Component;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'demo-tag-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with()
    {
        $rows = $this->listQuery(DemoTag::class)->paginate($this->perPage);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }
};
?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list/>
</x-noerd::page>
