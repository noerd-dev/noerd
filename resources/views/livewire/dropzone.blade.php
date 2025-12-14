<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Reactive;

new class extends Component {
    use WithFileUploads;

    #[Modelable]
    public ?array $files = [];

    #[Reactive]
    public array $rules = [];

    #[Reactive]
    public bool $multiple = false;

    public array $temporaryFiles = [];
    public array $uploadErrors = [];
    public bool $isUploading = false;
    public int $uploadProgress = 0;

    public function updatedTemporaryFiles(): void
    {
        $this->uploadErrors = [];
        if (!is_array($this->files)) {
            $this->files = [];
        }
        $this->validate([
            'temporaryFiles' => $this->multiple ? 'array' : 'required',
            'temporaryFiles.*' => $this->rules,
        ], [
            'temporaryFiles.*.mimes' => __('noerd_file_format_error'),
            'temporaryFiles.*.max' => __('noerd_file_max_error'),
        ]);

        foreach ($this->temporaryFiles as $file) {
            // Konvertiere TemporaryUploadedFile zu Array-Format für MediaUploadService
            $this->files[] = [
                'name' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
                'size' => $file->getSize(),
                'path' => $file->getRealPath(),
                'mime_type' => $file->getMimeType(),
                // Behalte auch das Original-Objekt für die Anzeige
                '_original' => $file
            ];
        }

        $this->temporaryFiles = [];
        $this->dispatch('files-updated', files: $this->files);
    }

    public function removeFile($index): void
    {
        // Lösche temporäre Datei wenn vorhanden
        if (is_array($this->files) && isset($this->files[$index]['_original'])) {
            try {
                $this->files[$index]['_original']->delete();
            } catch (\Exception $e) {
                // Fehler beim Löschen ignorieren
            }
        }

        if (is_array($this->files)) {
            unset($this->files[$index]);
            $this->files = array_values($this->files);
        } else {
            $this->files = [];
        }
        $this->dispatch('files-updated', files: $this->files);
    }

    public function clearFiles(): void
    {
        // Lösche alle temporären Dateien
        if (is_array($this->files)) {
            foreach ($this->files as $file) {
                if (isset($file['_original'])) {
                    try {
                        $file['_original']->delete();
                    } catch (\Exception $e) {
                        // Fehler beim Löschen ignorieren
                    }
                }
            }
        }

        $this->files = [];
        $this->dispatch('files-cleared');
    }

    public function getAcceptAttribute(): string
    {
        $mimes = [];
        foreach ($this->rules as $rule) {
            if (str_starts_with($rule, 'mimes:')) {
                $extensions = explode(',', str_replace('mimes:', '', $rule));
                foreach ($extensions as $ext) {
                    $mimes[] = '.' . $ext;
                }
            }
        }
        return implode(',', $mimes);
    }

    public function getMaxSizeAttribute(): string
    {
        foreach ($this->rules as $rule) {
            if (str_starts_with($rule, 'max:')) {
                $kb = str_replace('max:', '', $rule);
                return $this->formatBytes($kb * 1024);
            }
        }
        return __('noerd_unlimited');
    }

    private function formatBytes($bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' Bytes';
        }
    }

    public function getFileDisplayName($file): string
    {
        if (isset($file['_original'])) {
            return $file['_original']->getClientOriginalName();
        }
        return $file['name'] ?? __('noerd_unknown_file');
    }

    public function getFileSize($file): int
    {
        if (isset($file['_original'])) {
            return $file['_original']->getSize();
        }
        return $file['size'] ?? 0;
    }
}; ?>

<div class="w-full">
    <!-- Dropzone Area -->
    <div
        x-data="{
            isDragging: false,
            handleDrop(e) {
                this.isDragging = false;
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    @this.uploadMultiple('temporaryFiles', files);
                }
            }
        }"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop($event)"
        :class="{ 'border-blue-500 bg-blue-50': isDragging }"
        class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 transition-all duration-200 hover:border-gray-400"
    >
        <div class="text-center">
            <!-- Upload Icon -->
            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <!-- Upload Text -->
            <p class="mt-2 text-sm text-gray-600">
                <label for="file-upload-{{ $this->getId() }}" class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500">
                    <span>{{ __('noerd_select_file') }}</span>
                    <input
                        id="file-upload-{{ $this->getId() }}"
                        wire:model.live="temporaryFiles"
                        type="file"
                        class="sr-only"
                        accept="{{ $this->getAcceptAttribute() }}"
                        @if($multiple) multiple @endif
                    >
                </label>
                <span class="text-gray-500"> {{ __('noerd_drag_drop') }}</span>
            </p>

            <!-- File Info -->
            <p class="mt-1 text-xs text-gray-500">
                @if($this->getAcceptAttribute())
                    Erlaubte Dateitypen: {{ str_replace('.', '', $this->getAcceptAttribute()) }}
                @endif
                @if($this->getMaxSizeAttribute() !== 'unbegrenzt')
                    <br>Max. Dateigröße: {{ $this->getMaxSizeAttribute() }}
                @endif
            </p>
        </div>

        <!-- Upload Progress -->
        <div wire:loading wire:target="temporaryFiles" class="absolute inset-0 bg-white bg-opacity-90 flex items-center justify-center rounded-lg">
            <div class="text-center">
                <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-sm text-gray-600">Wird hochgeladen...</p>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    @if($uploadErrors)
        <div class="mt-2 text-sm text-red-600">
            @foreach($uploadErrors as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @error('temporaryFiles.*')
    <div class="mt-2 text-sm text-red-600">
        {{ $message }}
    </div>
    @enderror

    <!-- File List -->
    @php($fileCount = is_array($files) ? count($files) : 0)
    @if($fileCount > 0)
        <div class="mt-4 space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-700">Hochgeladene Dateien ({{ $fileCount }})</h4>
                @if($fileCount > 1)
                    <button
                        wire:click="clearFiles"
                        type="button"
                        class="text-xs text-red-600 hover:text-red-500"
                    >
                        Alle entfernen
                    </button>
                @endif
            </div>
            <ul class="divide-y divide-gray-200 border border-gray-200 rounded-lg">
                @foreach(is_array($files) ? $files : [] as $index => $file)
                    <li class="flex items-center justify-between py-3 px-4 hover:bg-gray-50">
                        <div class="flex items-center min-w-0 flex-1">
                            <!-- File Icon -->
                            <svg class="h-5 w-5 text-gray-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z" clip-rule="evenodd" />
                            </svg>

                            <!-- File Info -->
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $this->getFileDisplayName($file) }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $this->formatBytes($this->getFileSize($file)) }}
                                </p>
                            </div>
                        </div>

                        <!-- Remove Button -->
                        <button
                            wire:click="removeFile({{ $index }})"
                            type="button"
                            class="ml-4 flex-shrink-0 text-red-600 hover:text-red-500"
                        >
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
