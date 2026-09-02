<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

use Noerd\Commands\Concerns\AsksForHeroicon;
use Noerd\Traits\RequiresNoerdInstallation;

/**
 * Scaffolds a module: the Composer package, the ServiceProvider, the install/update
 * commands, the tenant-app migration stub, the dashboard, routes, navigation,
 * translations and the agent guidelines. Models and their list/detail screens are
 * NOT part of the scaffold — they are generated per model with
 * `noerd:make-resource {Model} --app={module}` once the model exists.
 */
class MakeModuleCommand extends Command
{
    use AsksForHeroicon;
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:module
                            {name? : The name of the module}
                            {--title= : The display title of the tenant app}
                            {--icon= : The heroicon of the tenant app (name or heroicon:outline:name)}
                            {--no-hints : Do not print the next steps (noerd:create-app runs them itself)}';

    protected $description = 'Create a new Noerd module with dashboard, install/update commands and directory structure';

    protected Filesystem $filesystem;

    protected string $moduleName;

    protected string $moduleNameStudly;

    protected string $appTitle;

    protected string $appIcon;

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
        if (! $this->ensureNoerdInstalled()) {
            return self::FAILURE;
        }

        // Get module name
        $name = $this->argument('name') ?? text(
            label: 'Module name (e.g. inventory, business-hours)',
            placeholder: 'inventory',
            required: true,
        );

        $this->moduleName = Str::kebab($name);
        $this->moduleNameStudly = Str::studly($name);
        $this->basePath = base_path('app-modules/' . $this->moduleName);

        // The tenant app the install command registers: title and heroicon. A module
        // ships no icon file — the app icon is a heroicon, exactly like a root app.
        $this->appTitle = $this->option('title') ?? text(
            label: 'App Title (display name, e.g. Inventory Management)',
            default: Str::headline($this->moduleName),
            required: true,
        );

        $icon = $this->option('icon');
        if ($icon === null && ! $this->input->isInteractive()) {
            $this->error('The --icon option is required when running non-interactively.');

            return self::FAILURE;
        }
        $this->appIcon = $this->normalizeHeroicon($icon ?? $this->askForHeroicon());

        $this->info("Creating Noerd module: {$this->moduleName}");
        $this->info("App title: {$this->appTitle} (icon {$this->appIcon})");
        $this->line('');

        // Check if module directory already exists
        if ($this->filesystem->isDirectory($this->basePath)) {
            $this->error("Module directory already exists: {$this->basePath}");

            return self::FAILURE;
        }

        try {
            $this->createDirectoryStructure();
            $this->createComposerJson();
            $this->createServiceProvider();
            $this->createInstallCommand();
            $this->createUpdateCommand();
            $this->createRoutes();
            $this->createTenantAppMigrationStub();
            $this->createDashboard();
            $this->createNavigation();
            $this->createTranslations();
            $this->createAgentDocs();
            $this->createGitkeep();
            $this->updateMainComposerJson();

            $this->line('');
            $this->info('Module successfully created!');

            if ($this->option('no-hints')) {
                return self::SUCCESS;
            }

            $this->line('');
            $this->warn('Next steps:');
            $this->line("  1. composer update noerd/{$this->moduleName}");
            $this->line("  2. php artisan noerd:install-{$this->moduleName}");
            $this->line("  3. Create a model + migration in the module, then php artisan noerd:make-resource {Model} --app={$this->moduleName}");
            $this->line("  4. Add \"noerd/{$this->moduleName}\" to the packages in boost.json and run php artisan boost:update (optional, for AI agents)");

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error creating module: ' . $e->getMessage());

            return self::FAILURE;
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
            'resources/boost/guidelines',
            'database/migrations',
            'database/factories',
            'database/seeders',
            'routes',
            'tests/Traits',
            'tests/Components',
            "app-configs/{$this->moduleName}/lists",
            "app-configs/{$this->moduleName}/details",
            "app-configs/{$this->moduleName}/pages",
            'app-configs/stubs',
        ];

        foreach ($directories as $dir) {
            $this->filesystem->makeDirectory("{$this->basePath}/{$dir}", 0755, true);
        }

        $this->line('<info>Created:</info> directory structure');
    }

    private function createComposerJson(): void
    {
        $content = $this->getStub('composer.stub');
        $this->filesystem->put("{$this->basePath}/composer.json", $content);
        $this->line('<info>Created:</info> composer.json');
    }

    private function createServiceProvider(): void
    {
        $content = $this->getStub('service-provider.stub');
        $path = "{$this->basePath}/src/Providers/{$this->moduleNameStudly}ServiceProvider.php";
        $this->filesystem->put($path, $content);
        $this->line('<info>Created:</info> ServiceProvider');
    }

    private function createInstallCommand(): void
    {
        $content = $this->getStub('install-command.stub');
        $path = "{$this->basePath}/src/Commands/{$this->moduleNameStudly}InstallCommand.php";
        $this->filesystem->put($path, $content);
        $this->line('<info>Created:</info> InstallCommand');
    }

    private function createUpdateCommand(): void
    {
        $content = $this->getStub('update-command.stub');
        $path = "{$this->basePath}/src/Commands/{$this->moduleNameStudly}UpdateCommand.php";
        $this->filesystem->put($path, $content);
        $this->line('<info>Created:</info> update command');
    }

    private function createAgentDocs(): void
    {
        $this->filesystem->put(
            "{$this->basePath}/resources/boost/guidelines/core.blade.php",
            $this->getStub('boost-guideline.stub'),
        );
        $this->filesystem->put("{$this->basePath}/AGENTS.md", $this->getStub('agents.stub'));
        $this->filesystem->put("{$this->basePath}/CLAUDE.md", $this->getStub('claude.stub'));
        $this->line('<info>Created:</info> agent guidelines (Boost guideline, AGENTS.md, CLAUDE.md)');
    }

    private function createRoutes(): void
    {
        $content = $this->getStub('routes.stub');
        $this->filesystem->put("{$this->basePath}/routes/{$this->moduleName}-routes.php", $content);
        $this->line('<info>Created:</info> routes');
    }

    /**
     * The tenant-app migration the install command publishes into the host
     * (`HasModuleInstallation::getMigrationStubPath()`), so non-interactive deploys
     * register the app through `php artisan migrate`. Its `{{APP_*}}` placeholders
     * are filled at install time, not here.
     */
    private function createTenantAppMigrationStub(): void
    {
        $content = $this->getStub('tenant-app-migration.stub');
        $this->filesystem->put(
            "{$this->basePath}/app-configs/stubs/add_{$this->moduleName}_tenant_app.php.stub",
            $content,
        );
        $this->line('<info>Created:</info> tenant app migration stub');
    }

    /**
     * Every app ships its own dashboard — the module's main route opens it. The
     * template is shared with noerd:make-dashboard (it carries no placeholders).
     */
    private function createDashboard(): void
    {
        $stubPath = dirname($this->stubPath) . '/resource/dashboard.blade.stub';

        if (! file_exists($stubPath)) {
            throw new Exception('Stub not found: dashboard.blade.stub');
        }

        $this->filesystem->put(
            "{$this->basePath}/resources/views/components/{$this->moduleName}-dashboard.blade.php",
            file_get_contents($stubPath),
        );
        $this->line('<info>Created:</info> dashboard');
    }

    private function createNavigation(): void
    {
        $content = $this->getStub('navigation.stub');
        $this->filesystem->put(
            "{$this->basePath}/app-configs/{$this->moduleName}/navigation.yml",
            $content,
        );
        $this->line('<info>Created:</info> navigation');
    }

    private function createTranslations(): void
    {
        // English is the key itself (flat JSON translations) — only de.json is generated.
        $deContent = $this->getStub('lang-de.stub');
        $this->filesystem->put("{$this->basePath}/resources/lang/de.json", $deContent);

        $this->line('<info>Created:</info> Translations');
    }

    private function createGitkeep(): void
    {
        $dirs = [
            'src/Models',
            'database/migrations',
            'database/factories',
            'database/seeders',
            'tests/Traits',
            'tests/Components',
            "app-configs/{$this->moduleName}/lists",
            "app-configs/{$this->moduleName}/details",
            "app-configs/{$this->moduleName}/pages",
        ];

        foreach ($dirs as $dir) {
            $this->filesystem->put("{$this->basePath}/{$dir}/.gitkeep", '');
        }
    }

    private function updateMainComposerJson(): void
    {
        $composerJsonPath = base_path('composer.json');
        $definition = json_decode($this->filesystem->get($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);

        if (! isset($definition['require'])) {
            $definition['require'] = [];
        }

        $composerName = "noerd/{$this->moduleName}";

        if (! isset($definition['require'][$composerName])) {
            $definition['require'][$composerName] = '*';
            $definition['require'] = $this->sortComposerPackages($definition['require']);

            $json = json_encode(
                $definition,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
            $this->filesystem->put($composerJsonPath, $json . "\n");
            $this->line("<info>Updated:</info> main composer.json (added {$composerName})");
        }
    }

    private function getStub(string $name): string
    {
        $path = "{$this->stubPath}/{$name}";

        if (! file_exists($path)) {
            throw new Exception("Stub not found: {$name}");
        }

        $content = file_get_contents($path);

        return str_replace(
            [
                '{{module-name}}',
                '{{ModuleName}}',
                '{{MODULE_KEY}}',
                '{{table-prefix}}',
                '{{noerd-constraint}}',
                '{{AppTitle}}',
                '{{AppTitleYaml}}',
                '{{app-icon}}',
            ],
            [
                $this->moduleName,
                $this->moduleNameStudly,
                Str::upper($this->moduleName),
                $this->tablePrefix(),
                $this->noerdConstraint(),
                str_replace("'", "\\'", $this->appTitle),
                str_replace("'", "''", $this->appTitle),
                $this->appIcon,
            ],
            $content,
        );
    }

    /**
     * Module tables are prefixed with the module key so two modules never collide.
     */
    private function tablePrefix(): string
    {
        return Str::snake(str_replace('-', '_', $this->moduleName));
    }

    /**
     * The generated module requires the core version it was scaffolded with
     * (caret on major.minor, e.g. `^0.14`), read from the package's own
     * composer.json so the constraint never lags behind a release.
     */
    private function noerdConstraint(): string
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
        $version = mb_ltrim((string) ($composer['version'] ?? ''), 'v');

        if (! preg_match('/^(\d+)\.(\d+)/', $version, $matches)) {
            return '*';
        }

        return "^{$matches[1]}.{$matches[2]}";
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
