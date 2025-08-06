<?php

use Illuminate\Support\Facades\File;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;
// use Symfony\Component\Yaml\Yaml;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'model-component';
    public const LIST_COMPONENT = 'models-table';
    public const ID = 'modelId';

    #[Url(keep: false, except: '')]
    public ?string $modelId = null;

    public array $model = [];
    public array $modal = [];

    public string $fileName = '';
    public string $filePath = '';
    public string $fileType = '';
    public int $fileSize = 0;
    public int $fileModified = 0;

    // YAML structure for editing
    public string $componentTitle = '';
    public string $componentDescription = '';
    public array $componentFields = [];

    public function mount(): void
    {
        // Initialize empty model
        $this->model = [
            'name' => '',
            'type' => 'default',
            'content' => '',
        ];

        if ($this->modelId) {
            $this->loadModelFile();
            $this->parseYamlStructure();
        } else {
            $this->initializeNewComponent();
        }

        //$this->mountModalProcess(self::COMPONENT, (object)$this->model);
    }

    private function loadModelFile(): void
    {
        // Parse modelId to get type and filename
        if (str_starts_with($this->modelId, 'default_')) {
            $this->fileType = 'default';
            $filename = substr($this->modelId, 8) . '.yml';
            $this->filePath = base_path('content/components/default/' . $filename);
        } elseif (str_starts_with($this->modelId, 'admin_')) {
            $this->fileType = 'admin';
            $filename = substr($this->modelId, 6) . '.yml';
            $this->filePath = base_path('content/components/admin/' . $filename);
        } else {
            return;
        }

        if (File::exists($this->filePath)) {
            $this->fileName = $filename;
            $content = File::get($this->filePath);
            $this->fileSize = File::size($this->filePath);
            $this->fileModified = File::lastModified($this->filePath);

            $this->model = [
                'name' => pathinfo($filename, PATHINFO_FILENAME),
                'type' => $this->fileType,
                'content' => $content,
            ];
        }
    }

    private function parseYamlStructure(): void
    {
        if (!empty($this->model['content'])) {
            try {
                $content = $this->model['content'];
                
                // Extract title
                if (preg_match('/^title:\s*(.*)$/m', $content, $matches)) {
                    $this->componentTitle = $this->cleanLabelValue(trim($matches[1]));
                }
                
                // Extract description
                if (preg_match('/^description:\s*(.*)$/m', $content, $matches)) {
                    $this->componentDescription = $this->cleanLabelValue(trim($matches[1]));
                }
                
                // Extract fields using regex for the compact YAML format
                $this->componentFields = [];
                if (preg_match('/fields:\s*\n((?:\s*-\s*\{[^}]+\}\s*\n?)+)/s', $content, $matches)) {
                    $fieldsBlock = $matches[1];
                    
                    // Parse each field line - improved regex to capture colspan if present
                    preg_match_all('/\{\s*name:\s*([^,]+),\s*label:\s*([^,]+),\s*type:\s*([^,}]+)(?:,\s*colspan:\s*([^,}]+))?\s*[^}]*\}/', $fieldsBlock, $fieldMatches, PREG_SET_ORDER);
                    
                    foreach ($fieldMatches as $fieldMatch) {
                        $this->componentFields[] = [
                            'name' => trim($fieldMatch[1]),
                            'label' => $this->cleanLabelValue(trim($fieldMatch[2])),
                            'type' => trim($fieldMatch[3]),
                            'colspan' => isset($fieldMatch[4]) ? trim($fieldMatch[4]) : '',
                            'required' => false,
                            'placeholder' => '',
                        ];
                    }
                }
            } catch (Exception $e) {
                // If parsing fails, initialize empty structure
                $this->initializeNewComponent();
            }
        } else {
            $this->initializeNewComponent();
        }
    }

    private function initializeNewComponent(): void
    {
        $this->componentTitle = '';
        $this->componentDescription = '';
        $this->componentFields = [
            [
                'name' => 'model.name',
                'label' => 'Name',
                'type' => 'text',
                'colspan' => '',
                'required' => false,
                'placeholder' => '',
            ]
        ];
    }

    public function addField(): void
    {
        $this->componentFields[] = [
            'name' => 'model.',
            'label' => '',
            'type' => 'text',
            'colspan' => '',
            'required' => false,
            'placeholder' => '',
        ];
    }

    public function removeField(int $fieldIndex): void
    {
        if (isset($this->componentFields[$fieldIndex])) {
            array_splice($this->componentFields, $fieldIndex, 1);
        }
    }

    private function generateYamlContent(): string
    {
        $titleWithQuotes = $this->addQuotesToLabel($this->componentTitle);
        $descriptionWithQuotes = $this->addQuotesToLabel($this->componentDescription);
        
        $yaml = "title: {$titleWithQuotes}\n";
        $yaml .= "description: {$descriptionWithQuotes}\n";
        $yaml .= "fields:\n";
        
        foreach ($this->componentFields as $field) {
            $labelWithQuotes = $this->addQuotesToLabel($field['label']);
            $yaml .= "  - { name: {$field['name']}, label: {$labelWithQuotes}, type: {$field['type']}";
            
            // Add colspan if it's set and not empty
            if (!empty($field['colspan'])) {
                $yaml .= ", colspan: {$field['colspan']}";
            }
            
            $yaml .= " }\n";
        }
        
        return $yaml;
    }
    
    private function cleanLabelValue(string $label): string
    {
        // Remove surrounding quotes from label for display
        $label = trim($label);
        
        // Remove single quotes
        if (str_starts_with($label, "'") && str_ends_with($label, "'")) {
            $label = substr($label, 1, -1);
        }
        
        // Remove double quotes
        if (str_starts_with($label, '"') && str_ends_with($label, '"')) {
            $label = substr($label, 1, -1);
        }
        
        return $label;
    }
    
    private function addQuotesToLabel(string $label): string
    {
        // Always add single quotes around label for YAML output
        $label = trim($label);
        
        // If label is empty, return empty quotes
        if (empty($label)) {
            return "''";
        }
        
        // Escape single quotes by doubling them
        $escapedLabel = str_replace("'", "''", $label);
        
        // Always wrap in single quotes for consistency
        return "'" . $escapedLabel . "'";
    }

    public function store(): void
    {
        $this->validate([
            'componentTitle' => ['required', 'string', 'max:255'],
        ]);

        // Generate YAML content
        $yamlContent = $this->generateYamlContent();

        // Save file
        if ($this->filePath && File::exists(dirname($this->filePath))) {
            File::put($this->filePath, $yamlContent);
            $this->model['content'] = $yamlContent;
            $this->loadModelFile(); // Refresh file info
            $this->lastChangeTime = time();

            session()->flash('success', 'Modell erfolgreich gespeichert.');
        }
    }

    public function delete(): void
    {
        if ($this->filePath && File::exists($this->filePath)) {
            File::delete($this->filePath);
            $this->closeModalProcess(self::LIST_COMPONENT);
        }
    }

    public function downloadFile()
    {
        if ($this->filePath && File::exists($this->filePath)) {
            return response()->download($this->filePath, $this->fileName);
        }
    }

    public function getFieldTypes(): array
    {
        $fieldTypes = [];
        
        // Scan default directory
        $defaultPath = base_path('content/components/default');
        if (File::exists($defaultPath)) {
            $this->scanDirectoryForTypes($defaultPath, $fieldTypes);
        }
        
        // Scan admin directory
        $adminPath = base_path('content/components/admin');
        if (File::exists($adminPath)) {
            $this->scanDirectoryForTypes($adminPath, $fieldTypes);
        }
        
        // Sort alphabetically and ensure we have some fallbacks
        ksort($fieldTypes);
        
        // Add common fallbacks if no types found
        if (empty($fieldTypes)) {
            $fieldTypes = [
                'text' => 'text',
                'email' => 'email',
                'textarea' => 'textarea',
            ];
        }
        
        return $fieldTypes;
    }
    
    private function scanDirectoryForTypes(string $path, array &$fieldTypes): void
    {
        $files = File::files($path);
        foreach ($files as $file) {
            if ($file->getExtension() === 'yml') {
                $content = File::get($file->getPathname());
                
                // Find all type: values with better regex that handles various formats
                preg_match_all('/type:\s*([^,}\s\n]+)/i', $content, $typeMatches);
                
                foreach ($typeMatches[1] as $type) {
                    $cleanType = trim($type);
                    // Remove any trailing spaces or special characters
                    $cleanType = rtrim($cleanType, ' ');
                    if (!empty($cleanType)) {
                        $fieldTypes[$cleanType] = ucfirst($cleanType);
                    }
                }
            }
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ $componentTitle ?: 'Modell' }}</x-noerd::modal-title>
    </x-slot:header>

    @if($this->modelId)
        <!-- File Info Header -->
        <div class="bg-white border-b px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ $fileName }}</h3>
                    <span @class([
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                        'bg-blue-100 text-blue-800' => $fileType === 'default',
                        'bg-amber-100 text-amber-800' => $fileType === 'admin'
                    ])>
                        {{ $fileType }}
                    </span>
                    <span class="text-sm text-gray-500">{{ number_format($fileSize / 1024, 1) }} KB</span>
                    <span class="text-sm text-gray-500">{{ date('d.m.Y H:i', $fileModified) }}</span>
                </div>
                <button wire:click="downloadFile"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <x-noerd::icons.arrow-down-tray class="h-4 w-4 mr-2"/>
                    Download
                </button>
            </div>
        </div>
    @endif

        <!-- Component Editor -->
    <div class="p-6 space-y-6">
        <!-- Component Meta -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Komponenten-Einstellungen</h3>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label for="componentTitle" class="block text-sm font-medium text-gray-700">Titel</label>
                    <input type="text" 
                           wire:model="componentTitle" 
                           id="componentTitle"
                           placeholder="Sägewerk"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    @error('componentTitle')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="componentDescription" class="block text-sm font-medium text-gray-700">Beschreibung</label>
                    <input type="text" 
                           wire:model="componentDescription" 
                           id="componentDescription"
                           placeholder="Beschreibung der Komponente"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    @error('componentDescription')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Fields Editor -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">Felder</h3>
                <button wire:click="addField"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                    <x-noerd::icons.plus class="h-4 w-4 mr-2"/>
                    Neues Feld
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                @forelse($componentFields as $fieldIndex => $field)
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                                <input type="text" 
                                       wire:model="componentFields.{{ $fieldIndex }}.name"
                                       placeholder="model.name"
                                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Label</label>
                                <input type="text" 
                                       wire:model="componentFields.{{ $fieldIndex }}.label"
                                       placeholder="Name"
                                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Typ</label>
                                <select wire:model="componentFields.{{ $fieldIndex }}.type"
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    @foreach($this->getFieldTypes() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Colspan</label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" 
                                           wire:model="componentFields.{{ $fieldIndex }}.colspan"
                                           placeholder="6"
                                           min="1" 
                                           max="12"
                                           class="flex-1 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <button wire:click="removeField({{ $fieldIndex }})"
                                            wire:confirm="Feld wirklich löschen?"
                                            class="inline-flex items-center px-2 py-2 border border-red-300 shadow-sm text-sm font-medium rounded text-red-700 bg-white hover:bg-red-50">
                                        <x-noerd::icons.trash class="h-4 w-4"/>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <x-noerd::icons.document class="mx-auto h-12 w-12 text-gray-400"/>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Noch keine Felder</h3>
                        <p class="mt-1 text-sm text-gray-500">Fügen Sie ein neues Feld hinzu, um zu beginnen.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar
            deleteMessage="Die YAML-Datei wird permanent gelöscht!"
            showDelete="{{ isset($modelId) && $modelId }}"/>
    </x-slot:footer>
</x-noerd::page>
