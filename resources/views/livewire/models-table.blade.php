<?php

use Illuminate\Support\Facades\File;
use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'models-table';

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch('set-app-id', ['id' => null]);
        
        $this->dispatch(
            event: 'noerdModal',
            component: 'model-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with()
    {
        $yamlFiles = [];
        
        // Scan default directory
        $defaultPath = base_path('content/components/default');
        if (File::exists($defaultPath)) {
            $defaultFiles = File::files($defaultPath);
            foreach ($defaultFiles as $file) {
                if ($file->getExtension() === 'yml') {
                    $yamlFiles[] = [
                        'id' => 'default_' . $file->getFilenameWithoutExtension(),
                        'name' => $file->getFilenameWithoutExtension(),
                        'filename' => $file->getFilename(),
                        'type' => 'default',
                        'size' => $file->getSize(),
                        'size_formatted' => number_format($file->getSize() / 1024, 1) . ' KB',
                        'modified' => $file->getMTime(),
                        'modified_formatted' => date('d.m.Y H:i', $file->getMTime()),
                        'relative_path' => 'content/components/default/' . $file->getFilename(),
                        'full_path' => $file->getPathname(),
                    ];
                }
            }
        }

        // Scan admin directory
        $adminPath = base_path('content/components/admin');
        if (File::exists($adminPath)) {
            $adminFiles = File::files($adminPath);
            foreach ($adminFiles as $file) {
                if ($file->getExtension() === 'yml') {
                    $yamlFiles[] = [
                        'id' => 'admin_' . $file->getFilenameWithoutExtension(),
                        'name' => $file->getFilenameWithoutExtension(),
                        'filename' => $file->getFilename(),
                        'type' => 'admin',
                        'size' => $file->getSize(),
                        'size_formatted' => number_format($file->getSize() / 1024, 1) . ' KB',
                        'modified' => $file->getMTime(),
                        'modified_formatted' => date('d.m.Y H:i', $file->getMTime()),
                        'relative_path' => 'content/components/admin/' . $file->getFilename(),
                        'full_path' => $file->getPathname(),
                    ];
                }
            }
        }

        // Sort by name
        usort($yamlFiles, fn($a, $b) => strcmp($a['name'], $b['name']));

        // Filter search
        if ($this->search) {
            $yamlFiles = array_filter($yamlFiles, function ($file) {
                return stripos($file['name'], $this->search) !== false ||
                       stripos($file['type'], $this->search) !== false;
            });
        }

        // Sort by field
        $sortField = $this->sortField ?? 'name';
        $sortAsc = $this->sortAsc ?? true;
        
        usort($yamlFiles, function ($a, $b) use ($sortField, $sortAsc) {
            $aValue = $a[$sortField] ?? '';
            $bValue = $b[$sortField] ?? '';
            
            if ($sortField === 'size' || $sortField === 'modified') {
                $result = $aValue <=> $bValue;
            } else {
                $result = strcmp($aValue, $bValue);
            }
            
            return $sortAsc ? $result : -$result;
        });

        // Paginate manually
        $perPage = self::PAGINATION;
        $total = count($yamlFiles);
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        $paginatedFiles = array_slice($yamlFiles, $offset, $perPage);
        
        // Create pagination object
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedFiles,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url()]
        );

        return [
            'rows' => $paginator,
        ];
    }

    public function rendering()
    {
        if (request()->modelId) {
            $this->tableAction(request()->modelId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <div>
        @include('noerd::components.table.table-build',
        [
            'title' => __('Modelle'),
            'newLabel' => __('Neues Modell'),
            'redirectAction' => '',
            'disableSearch' => false,
            'table' => [
                [
                    'width' => 20,
                    'field' => 'name',
                    'label' => __('Name'),
                ],
                [
                    'width' => 8,
                    'field' => 'type',
                    'label' => __('Typ'),
                    'type' => 'badge',
                    'badgeColors' => [
                        'default' => 'blue',
                        'admin' => 'amber'
                    ]
                ],
                [
                    'width' => 15,
                    'field' => 'relative_path',
                    'label' => __('Pfad'),
                    'type' => 'text',
                    'class' => 'text-xs text-gray-500 font-mono'
                ],
                [
                    'width' => 6,
                    'field' => 'size_formatted',
                    'type' => 'text',
                    'align' => 'right',
                    'label' => __('Größe'),
                ],
                [
                    'width' => 10,
                    'field' => 'modified_formatted',
                    'type' => 'text',
                    'label' => __('Geändert'),
                ],
            ],
        ])
    </div>
</x-noerd::page>