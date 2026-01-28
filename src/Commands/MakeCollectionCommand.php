<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeCollectionCommand extends Command
{
    protected $signature = 'noerd:make-collection
                            {name? : The collection name (kebab-case, e.g. "customers")}
                            {--app=setup : The target app folder in app-configs}';

    protected $description = 'Create a new collection YML file interactively';

    private array $fieldTypes = [
        'translatableText' => 'Translatable Text',
        'translatableTextarea' => 'Translatable Textarea',
        'text' => 'Text',
        'textarea' => 'Textarea',
        'checkbox' => 'Checkbox',
        'select' => 'Select',
        'image' => 'Image',
        'date' => 'Date',
        'datetime' => 'DateTime',
        'number' => 'Number',
    ];

    public function handle(): int
    {
        $this->info('Creating a new collection...');
        $this->newLine();

        // 1. Get collection name
        $name = $this->argument('name');
        if (empty($name)) {
            $name = text(
                label: 'Collection name (kebab-case)',
                placeholder: 'customers',
                required: true,
                validate: fn(string $value) => $this->validateName($value),
            );
        }
        $name = mb_strtolower(mb_trim($name));

        // 2. Get title (singular)
        $title = text(
            label: 'Title (singular)',
            placeholder: 'Kunde',
            required: true,
        );

        // 3. Get titleList (plural)
        $titleList = text(
            label: 'Title list (plural)',
            placeholder: 'Kunden',
            default: $title,
            required: true,
        );

        // 4. Get key (auto-generated from name)
        $defaultKey = mb_strtoupper(str_replace('-', '_', $name));
        $key = text(
            label: 'Key (uppercase)',
            placeholder: $defaultKey,
            default: $defaultKey,
            required: true,
            validate: fn(string $value) => preg_match('/^[A-Z][A-Z0-9_]*$/', $value) ? null : 'Key must be uppercase letters, numbers and underscores only.',
        );

        // 5. Get button text
        $buttonList = text(
            label: 'Button text (for "New Entry" button)',
            placeholder: 'Neuer Eintrag',
            default: 'Neuer Eintrag',
            required: true,
        );

        // 6. Get description (optional)
        $description = text(
            label: 'Description (optional)',
            placeholder: '',
            default: '',
        );

        // 7. Add fields interactively
        $fields = [];
        $this->newLine();
        $this->info('Now add fields to the collection:');

        do {
            $field = $this->askForField(count($fields) + 1);
            if ($field) {
                $fields[] = $field;
                $this->line("  ✓ Added field: {$field['name']}");
            }

            $addMore = confirm(
                label: 'Add another field?',
                default: count($fields) < 3,
            );
        } while ($addMore);

        if (empty($fields)) {
            $this->warn('No fields added. Adding a default title field.');
            $fields[] = ['name' => 'model.title', 'label' => 'noerd_label_title', 'type' => 'translatableText', 'colspan' => 6];
        }

        // Build the collection array
        $collection = [
            'title' => $title,
            'titleList' => $titleList,
            'key' => $key,
            'buttonList' => $buttonList,
            'description' => $description,
            'hasPage' => false,
            'fields' => $fields,
        ];

        // Determine target path
        $app = $this->option('app');
        $targetDir = base_path("app-configs/{$app}/collections");

        if (! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0755, true)) {
                $this->error("Failed to create directory: {$targetDir}");

                return self::FAILURE;
            }
            $this->info("Created directory: app-configs/{$app}/collections/");
        }

        $targetFile = "{$targetDir}/{$name}.yml";

        if (file_exists($targetFile)) {
            if (! confirm("File {$name}.yml already exists. Overwrite?", false)) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        // Generate YAML with inline field format
        $yaml = $this->generateYaml($collection);

        file_put_contents($targetFile, $yaml);

        $this->newLine();
        $this->info("✅ Collection created: app-configs/{$app}/collections/{$name}.yml");

        return self::SUCCESS;
    }

    private function validateName(string $value): ?string
    {
        if (mb_strlen($value) < 2) {
            return 'Name must be at least 2 characters.';
        }
        if (! preg_match('/^[a-z][a-z0-9-]*$/', $value)) {
            return 'Name must be lowercase letters, numbers and hyphens only.';
        }

        return null;
    }

    private function askForField(int $number): ?array
    {
        $this->newLine();
        $this->line("Field #{$number}:");

        $name = text(
            label: 'Field name',
            placeholder: 'model.name',
            required: true,
            validate: function (string $value) {
                if (! preg_match('/^[a-z][a-z0-9_.]*$/i', $value)) {
                    return 'Field name must contain only letters, numbers, dots and underscores.';
                }

                return null;
            },
        );

        $label = text(
            label: 'Label (or translation key)',
            placeholder: 'Name',
            required: true,
        );

        $type = select(
            label: 'Field type',
            options: $this->fieldTypes,
            default: 'translatableText',
        );

        $colspan = (int) text(
            label: 'Colspan (1-12)',
            placeholder: '6',
            default: '6',
            validate: function (string $value) {
                $num = (int) $value;
                if ($num < 1 || $num > 12) {
                    return 'Colspan must be between 1 and 12.';
                }

                return null;
            },
        );

        return [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'colspan' => $colspan,
        ];
    }

    private function generateYaml(array $collection): string
    {
        $lines = [];
        $lines[] = "title: '{$collection['title']}'";
        $lines[] = "titleList: '{$collection['titleList']}'";
        $lines[] = "key: '{$collection['key']}'";
        $lines[] = "buttonList: '{$collection['buttonList']}'";
        $lines[] = "description: '{$collection['description']}'";
        $lines[] = 'hasPage: ' . ($collection['hasPage'] ? 'true' : 'false');
        $lines[] = 'fields:';

        foreach ($collection['fields'] as $field) {
            $parts = [];
            $parts[] = "name: {$field['name']}";
            $parts[] = "label: {$field['label']}";
            $parts[] = "type: {$field['type']}";
            $parts[] = "colspan: {$field['colspan']}";
            $lines[] = '  - { ' . implode(', ', $parts) . ' }';
        }

        return implode("\n", $lines) . "\n";
    }
}
