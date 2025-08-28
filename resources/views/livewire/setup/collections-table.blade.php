<?php

use Illuminate\Support\Facades\File;
use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'collections-table';

    public function mount()
    {
        if (request()->create) {
            $this->tableAction();
        }

        if (request()->file) {
            $this->editFile(request()->file);
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'collection-component',
            source: self::COMPONENT,
            arguments: ['fileName' => $modelId, 'relationId' => $relationId],
        );
    }

    public function editFile($fileName): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'collection-component.blade.php',
            source: self::COMPONENT,
            arguments: ['fileName' => $fileName],
        );
    }

    public function deleteFile($fileName): void
    {
        $filePath = storage_path('environment/collections/' . $fileName);

        if (File::exists($filePath)) {
            File::delete($filePath);
            $this->dispatch('noerd-notification', [
                'type' => 'success',
                'message' => 'Collection-Datei wurde erfolgreich gelöscht.'
            ]);
        }
    }

    public function with(): array
    {
        $collectionsPath = storage_path('environment/collections');
        $files = [];

        if (File::exists($collectionsPath)) {
            $yamlFiles = File::files($collectionsPath);

            foreach ($yamlFiles as $file) {
                if ($file->getExtension() === 'yml') {
                    $fileName = $file->getFilename();
                    $lastModified = File::lastModified($file->getPathname());

                    if (empty($this->search) || str_contains(strtolower($fileName), strtolower($this->search))) {
                        $files[] = [
                            'id' => $fileName,
                            'name' => str_replace('.yml', '', $fileName),
                            'file_name' => $fileName,
                            'last_modified' => date('d.m.Y H:i', $lastModified),
                            'size' => $this->formatBytes($file->getSize()),
                        ];
                    }
                }
            }
        }

        // Sort by name
        usort($files, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return [
            'rows' => collect($files),
            'tableConfig' => [
                'title' => 'Collections',
                'newLabel' => 'Neue Collection',
                'disableSearch' => false,
                'columns' => [
                    ['field' => 'name', 'label' => 'Name', 'width' => 30],
                    ['field' => 'file_name', 'label' => 'Dateiname', 'width' => 25],
                    ['field' => 'last_modified', 'label' => 'Zuletzt geändert', 'width' => 20],
                    ['field' => 'size', 'label' => 'Größe', 'width' => 15],
                ]
            ],
        ];
    }

    private function formatBytes($size, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
</x-noerd::page>
