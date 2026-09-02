<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Support\SetupCollectionDefinitionImport;
use Symfony\Component\Yaml\Yaml;

class MakeCollectionCommand extends Command
{
    protected $signature = 'noerd:make-collection
                            {name? : The collection name (kebab-case, e.g. "customers")}
                            {--app=setup : The target app folder in app-configs}';

    protected $description = 'Create a new collection YAML file interactively';

    private array $fieldTypes = SetupCollectionHelper::FIELD_TYPES;

    public function handle(): int
    {
        // A field-by-field wizard has no scripted form: it needs a terminal.
        if (! $this->input->isInteractive()) {
            $this->error('noerd:make-collection is an interactive wizard and cannot run with --no-interaction. Create the YAML file by hand or import one with noerd:setup-collections:import-yaml.');

            return self::FAILURE;
        }

        $this->info('Creating a new collection...');

        // The written file is inert while the schemas are read from the
        // database — say so before the user fills in a whole definition.
        if (SetupCollectionDefinitionImport::isDatabaseMode()) {
            $this->warn('Collections run in "database" mode (noerd.collections.mode).');
            $this->warn('This command only writes a YAML file, which is NOT read in that mode.');
            $this->warn('Create the collection in Setup > Collection Definitions instead, or import');
            $this->warn('the file afterwards with: php artisan noerd:setup-collections:import-yaml');
        }

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
            placeholder: 'Customer',
            required: true,
        );

        // 3. Get titleList (plural)
        $titleList = text(
            label: 'Title list (plural)',
            placeholder: 'Customers',
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
            placeholder: 'New Entry',
            default: 'New Entry',
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
                $this->line("  Added field: {$field['name']}");
            }

            $addMore = confirm(
                label: 'Add another field?',
                default: count($fields) < 3,
            );
        } while ($addMore);

        if (empty($fields)) {
            $this->warn('No fields added. Adding a default title field.');
            $fields[] = ['name' => 'detailData.title', 'label' => 'Title', 'type' => 'translatableText', 'colspan' => 6];
        }

        // Build the collection array
        $collection = [
            'title' => $title,
            'titleList' => $titleList,
            'key' => $key,
            'buttonList' => $buttonList,
            'description' => $description,
            'fields' => $fields,
        ];

        // Determine target path
        $app = $this->option('app');
        $targetDir = base_path("app-configs/{$app}/collections");

        if (! File::isDirectory($targetDir)) {
            File::ensureDirectoryExists($targetDir);
            $this->info("Created directory: app-configs/{$app}/collections/");
        }

        $targetFile = "{$targetDir}/{$name}.yml";

        if (File::exists($targetFile)) {
            if (! confirm("File {$name}.yml already exists. Overwrite?", false)) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        $yaml = $this->generateYaml($collection);

        File::put($targetFile, $yaml);

        $this->newLine();
        $this->info("Collection created: app-configs/{$app}/collections/{$name}.yml");

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
            placeholder: 'detailData.name',
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
        // Block style throughout — flow style (`{ key: value }`) is never written.
        return Yaml::dump($collection, 4, 2);
    }
}
