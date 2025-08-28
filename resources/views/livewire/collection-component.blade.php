<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'collection-component';
    public const LIST_COMPONENT = 'collections-table';

    public ?string $fileName = null;
    public string $yamlContent = '';
    public string $originalFileName = '';
    public bool $isNewFile = false;

    public function mount(): void
    {
        $this->fileName = $this->fileName ?? request()->get('fileName');

        if ($this->fileName) {
            $this->originalFileName = $this->fileName;
            $this->loadFile();
        } else {
            $this->isNewFile = true;
            $this->fileName = '';
            $this->yamlContent = $this->getExampleYaml();
        }
    }

    private function loadFile(): void
    {
        $filePath = storage_path('environment/collections/' . $this->fileName);

        if (File::exists($filePath)) {
            $this->yamlContent = File::get($filePath);
        } else {
            $this->dispatch('noerd-notification', [
                'type' => 'error',
                'message' => 'Datei nicht gefunden.'
            ]);
            $this->closeModalProcess(self::LIST_COMPONENT);
        }
    }

    private function getExampleYaml(): string
    {
        return "title: 'Neue Collection'
titleList: 'Neue Collection Liste'
key: 'NEW_COLLECTION'
buttonList: 'Neuer Eintrag'
description: 'Beschreibung der Collection'
hasPage: true
fields:
  - { name: model.name, label: Name, type: translatableText, colspan: 6 }
  - { name: model.description, label: Beschreibung, type: translatableTextarea, colspan: 12 }
  - { name: image, label: Bild, type: image, colspan: 6 }
";
    }

    public function store(): void
    {
        $this->validate([
            'fileName' => ['required', 'string'],
            'yamlContent' => ['required', 'string'],
        ], [
            'fileName.required' => 'Dateiname ist erforderlich.',
            'fileName.regex' => 'Dateiname darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
            'yamlContent.required' => 'YAML-Inhalt ist erforderlich.',
        ]);

        // Ensure filename has .yml extension
        $fileName = $this->fileName;
        if (!Str::endsWith($fileName, '.yml')) {
            $fileName .= '.yml';
        }

        // Check if filename changed and new file already exists
        if ($this->isNewFile || $fileName !== $this->originalFileName) {
            $filePath = storage_path('environment/collections/' . $fileName);
            if (File::exists($filePath)) {
                $this->addError('fileName', 'Eine Datei mit diesem Namen existiert bereits.');
                return;
            }
        }

        // Basic YAML syntax validation
        if (!$this->validateYamlSyntax($this->yamlContent)) {
            $this->addError('yamlContent', 'Ungültige YAML-Syntax. Bitte überprüfen Sie die Einrückung und Struktur.');
            return;
        }

        // Save file
        $filePath = storage_path('environment/collections/' . $fileName);
        File::put($filePath, $this->yamlContent);

        // If filename changed, delete old file
        if (!$this->isNewFile && $fileName !== $this->originalFileName) {
            $oldFilePath = storage_path('environment/collections/' . $this->originalFileName);
            if (File::exists($oldFilePath)) {
                File::delete($oldFilePath);
            }
        }

        $this->dispatch('noerd-notification', [
            'type' => 'success',
            'message' => $this->isNewFile ? 'Collection-Datei wurde erfolgreich erstellt.' : 'Collection-Datei wurde erfolgreich gespeichert.'
        ]);

        $this->showSuccessIndicator = true;
        $this->closeModalProcess(self::LIST_COMPONENT);
    }

    public function delete(): void
    {
        if (!$this->isNewFile && $this->originalFileName) {
            $filePath = storage_path('environment/collections/' . $this->originalFileName);

            if (File::exists($filePath)) {
                File::delete($filePath);
                $this->dispatch('noerd-notification', [
                    'type' => 'success',
                    'message' => 'Collection-Datei wurde erfolgreich gelöscht.'
                ]);
            }
        }

        $this->closeModalProcess(self::LIST_COMPONENT);
    }

    private function validateYamlSyntax(string $yamlContent): bool
    {
        // Basic YAML validation without yaml_parse extension
        $lines = explode("\n", $yamlContent);
        $indentStack = [0];

        foreach ($lines as $lineNumber => $line) {
            $trimmed = trim($line);

            // Skip empty lines and comments
            if (empty($trimmed) || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Calculate indentation
            $indent = strlen($line) - strlen(ltrim($line));

            // Check for valid indentation (must be multiple of 2)
            if ($indent % 2 !== 0) {
                return false;
            }

            // Basic structure checks
            if (str_contains($line, ':')) {
                // Key-value pair
                $parts = explode(':', $line, 2);
                if (count($parts) !== 2) {
                    return false;
                }
            }

            // Check for tabs (YAML doesn't allow tabs for indentation)
            if (str_contains($line, "\t")) {
                return false;
            }
        }

        return true;
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>
            {{ $isNewFile ? 'Neue Collection erstellen' : 'Collection bearbeiten: ' . $originalFileName }}
        </x-noerd::modal-title>
    </x-slot:header>

    <div class="space-y-6">
        <!-- Filename Input -->
        <div>
            <flux:field>
                <flux:label>Dateiname</flux:label>
                <flux:input
                    wire:model="fileName"
                    placeholder="collection-name"
                    :readonly="!$isNewFile"
                />
                <flux:error name="fileName" />
                @if(!$isNewFile)
                    <flux:description>Dateiname kann bei bestehenden Dateien nicht geändert werden.</flux:description>
                @endif
            </flux:field>
        </div>

        <!-- YAML Editor -->
        <div>
            <flux:field>
                <flux:label>YAML-Inhalt</flux:label>
                <flux:textarea
                    wire:model="yamlContent"
                    rows="20"
                    class="font-mono text-sm"
                    style="white-space: pre; font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;"
                />
                <flux:error name="yamlContent" />
                <flux:description>
                    Bearbeiten Sie hier den YAML-Inhalt der Collection.
                    Achten Sie auf korrekte Einrückung und YAML-Syntax.
                </flux:description>
            </flux:field>
        </div>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="!$isNewFile" />
    </x-slot:footer>
</x-noerd::page>
