<?php

namespace Noerd\Commands;

use Composer\Factory;
use Composer\Json\JsonFile;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

use Noerd\Traits\RequiresNoerdInstallation;

class MakeModuleCommand extends Command
{
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:module {name? : The name of the module}';

    protected $description = 'Create a new Noerd module with complete directory structure';

    protected Filesystem $filesystem;

    protected string $moduleName;

    protected string $moduleNameStudly;

    protected string $modelName;

    protected string $modelNameStudly;

    protected string $modelNamePlural;

    protected string $basePath;

    protected string $stubPath;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
        $this->stubPath = __DIR__ . '/stubs/module';
    }

    public function handle(): int
    {
        // Ensure noerd:install has been run first
        if (!$this->ensureNoerdInstalled()) {
            return 1;
        }

        // Get module name
        $name = $this->argument('name') ?? text(
            label: 'What is the name of the module?',
            placeholder: 'inventory',
            required: true,
        );

        $this->moduleName = Str::kebab($name);
        $this->moduleNameStudly = Str::studly($name);
        $this->basePath = base_path('app-modules/' . $this->moduleName);

        // Get model name
        $modelInput = text(
            label: 'What is the main model name? (singular)',
            placeholder: 'item',
            default: Str::singular($this->moduleName),
            required: true,
        );

        $this->modelName = Str::kebab($modelInput);
        $this->modelNameStudly = Str::studly($modelInput);
        $this->modelNamePlural = Str::plural($this->modelName);

        $this->info("Creating Noerd module: {$this->moduleName}");
        $this->info("Main model: {$this->modelNameStudly}");
        $this->line('');

        // Check if module directory already exists
        if ($this->filesystem->isDirectory($this->basePath)) {
            $this->error("Module directory already exists: {$this->basePath}");

            return 1;
        }

        try {
            $this->createDirectoryStructure();
            $this->createComposerJson();
            $this->createServiceProvider();
            $this->createRoutes();
            $this->createModel();
            $this->createMigration();
            $this->createLivewireComponents();
            $this->createYamlConfigurations();
            $this->createTranslations();
            $this->createGitkeep();
            $this->updateMainComposerJson();

            $this->line('');
            $this->info('Module successfully created!');
            $this->line('');
            $this->warn('Next steps:');
            $this->line("  1. composer update noerd/{$this->moduleName}");
            $this->line('  2. php artisan migrate');
            $this->line("  3. php artisan noerd:create-app (Name: {$this->moduleNameStudly}, Route: {$this->moduleName}.index)");

            return 0;
        } catch (Exception $e) {
            $this->error('Error creating module: ' . $e->getMessage());

            return 1;
        }
    }

    private function createDirectoryStructure(): void
    {
        $directories = [
            'src/Providers',
            'src/Models',
            'src/Commands',
            'resources/views/components',
            'resources/lang',
            'database/migrations',
            'database/factories',
            'database/seeders',
            'routes',
            'tests/Traits',
            'tests/Components',
            "app-contents/{$this->moduleName}/lists",
            "app-contents/{$this->moduleName}/details",
        ];

        foreach ($directories as $dir) {
            $this->filesystem->makeDirectory("{$this->basePath}/{$dir}", 0755, true);
        }

        $this->line('<info>✓ Created:</info> directory structure');
    }

    private function createComposerJson(): void
    {
        $content = $this->getStub('composer.stub');
        $this->filesystem->put("{$this->basePath}/composer.json", $content);
        $this->line('<info>✓ Created:</info> composer.json');
    }

    private function createServiceProvider(): void
    {
        $content = $this->getStub('service-provider.stub');
        $path = "{$this->basePath}/src/Providers/{$this->moduleNameStudly}ServiceProvider.php";
        $this->filesystem->put($path, $content);
        $this->line('<info>✓ Created:</info> ServiceProvider');
    }

    private function createRoutes(): void
    {
        $content = $this->getStub('routes.stub');
        $this->filesystem->put("{$this->basePath}/routes/{$this->moduleName}-routes.php", $content);
        $this->line('<info>✓ Created:</info> routes');
    }

    private function createModel(): void
    {
        $content = <<<PHP
<?php

namespace Noerd\\{$this->moduleNameStudly}\\Models;

use Illuminate\Database\Eloquent\Model;

class {$this->modelNameStudly} extends Model
{
    protected \$guarded = ['id'];

    protected \$casts = [
        'is_active' => 'boolean',
    ];
}
PHP;

        $this->filesystem->put("{$this->basePath}/src/Models/{$this->modelNameStudly}.php", $content);
        $this->line('<info>✓ Created:</info> Model');
    }

    private function createMigration(): void
    {
        $table = Str::snake($this->modelNamePlural);
        $timestamp = date('Y_m_d_His');
        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            \$table->string('name');
            \$table->boolean('is_active')->default(true);
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;

        $filename = "{$timestamp}_create_{$table}_table.php";
        $this->filesystem->put("{$this->basePath}/database/migrations/{$filename}", $content);
        $this->line('<info>✓ Created:</info> Migration');
    }

    private function createLivewireComponents(): void
    {
        // List component
        $listContent = $this->getStub('list.stub');
        $this->filesystem->put(
            "{$this->basePath}/resources/views/components/{$this->modelNamePlural}-list.blade.php",
            $listContent,
        );

        // Detail component
        $detailContent = $this->getStub('detail.stub');
        $this->filesystem->put(
            "{$this->basePath}/resources/views/components/{$this->modelName}-detail.blade.php",
            $detailContent,
        );

        $this->line('<info>✓ Created:</info> Livewire components');
    }

    private function createYamlConfigurations(): void
    {
        // List YAML
        $listContent = $this->getStub('list-yaml.stub');
        $this->filesystem->put(
            "{$this->basePath}/app-contents/{$this->moduleName}/lists/{$this->modelNamePlural}-list.yml",
            $listContent,
        );

        // Detail YAML
        $detailContent = $this->getStub('detail-yaml.stub');
        $this->filesystem->put(
            "{$this->basePath}/app-contents/{$this->moduleName}/details/{$this->modelName}-detail.yml",
            $detailContent,
        );

        // Navigation YAML
        $navContent = $this->getStub('navigation.stub');
        $this->filesystem->put(
            "{$this->basePath}/app-contents/{$this->moduleName}/navigation.yml",
            $navContent,
        );

        $this->line('<info>✓ Created:</info> YAML configurations');
    }

    private function createTranslations(): void
    {
        $deContent = $this->getStub('lang-de.stub');
        $this->filesystem->put("{$this->basePath}/resources/lang/de.json", $deContent);

        $enContent = $this->getStub('lang-en.stub');
        $this->filesystem->put("{$this->basePath}/resources/lang/en.json", $enContent);

        $this->line('<info>✓ Created:</info> Translations');
    }

    private function createGitkeep(): void
    {
        $dirs = [
            'database/factories',
            'database/seeders',
            'src/Commands',
            'tests/Traits',
            'tests/Components',
        ];

        foreach ($dirs as $dir) {
            $this->filesystem->put("{$this->basePath}/{$dir}/.gitkeep", '');
        }
    }

    private function updateMainComposerJson(): void
    {
        $originalWorkingDir = getcwd();
        chdir(base_path());

        $jsonFile = new JsonFile(Factory::getComposerFile());
        $definition = $jsonFile->read();

        if (!isset($definition['require'])) {
            $definition['require'] = [];
        }

        $composerName = "noerd/{$this->moduleName}";

        if (!isset($definition['require'][$composerName])) {
            $definition['require'][$composerName] = '*';
            $definition['require'] = $this->sortComposerPackages($definition['require']);

            $jsonFile->write($definition);
            $this->line("<info>✓ Updated:</info> main composer.json (added {$composerName})");
        }

        chdir($originalWorkingDir);
    }

    private function getStub(string $name): string
    {
        $path = "{$this->stubPath}/{$name}";

        if (!file_exists($path)) {
            throw new Exception("Stub not found: {$name}");
        }

        $content = file_get_contents($path);

        return str_replace(
            [
                '{{module-name}}',
                '{{ModuleName}}',
                '{{model}}',
                '{{Model}}',
                '{{models}}',
                '{{Models}}',
                '{{ModelTitle}}',
                '{{ModelsTitle}}',
            ],
            [
                $this->moduleName,
                $this->moduleNameStudly,
                $this->modelName,
                $this->modelNameStudly,
                $this->modelNamePlural,
                Str::studly($this->modelNamePlural),
                ucfirst($this->modelName),
                ucfirst($this->modelNamePlural),
            ],
            $content,
        );
    }

    private function sortComposerPackages(array $packages): array
    {
        $prefix = fn($requirement) => preg_replace(
            [
                '/^php$/',
                '/^hhvm-/',
                '/^ext-/',
                '/^lib-/',
                '/^\D/',
                '/^(?!php$|hhvm-|ext-|lib-)/',
            ],
            [
                '0-$0',
                '1-$0',
                '2-$0',
                '3-$0',
                '4-$0',
                '5-$0',
            ],
            $requirement,
        );

        uksort($packages, fn($a, $b) => strnatcmp($prefix($a), $prefix($b)));

        return $packages;
    }
}
