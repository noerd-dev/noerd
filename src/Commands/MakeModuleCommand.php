<?php

namespace Noerd\Noerd\Commands;

use Composer\Factory;
use Composer\Json\JsonFile;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Noerd\Noerd\Traits\RequiresNoerdInstallation;

class MakeModuleCommand extends Command
{
    use RequiresNoerdInstallation;
    protected $signature = 'noerd:module {name : The name of the module}';

    protected $description = 'Initialize an existing module directory with composer.json';

    protected Filesystem $filesystem;

    protected string $moduleName;

    protected string $moduleNameStudly;

    protected string $basePath;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    public function handle(): int
    {
        // Ensure noerd:install has been run first
        if (! $this->ensureNoerdInstalled()) {
            return 1;
        }

        $this->moduleName = Str::kebab($this->argument('name'));
        $this->moduleNameStudly = Str::studly($this->argument('name'));
        $this->basePath = base_path('app-modules/' . $this->moduleName);

        $this->info("Initializing Noerd module: {$this->moduleName}");
        $this->line('');

        // Check if module directory exists
        if (! $this->filesystem->isDirectory($this->basePath)) {
            $this->error("Module directory not found: {$this->basePath}");
            $this->line('Please create the module directory first with ServiceProvider, routes, etc.');

            return 1;
        }

        try {
            $this->createComposerJson();
            $this->updateMainComposerJson();

            $this->line('');
            $this->info('Module successfully initialized!');
            $this->line('');
            $this->warn('Please run:');
            $this->line("  composer update noerd/{$this->moduleName}");

            return 0;
        } catch (Exception $e) {
            $this->error('Error initializing module: ' . $e->getMessage());

            return 1;
        }
    }

    private function createComposerJson(): void
    {
        $composerPath = "{$this->basePath}/composer.json";

        if (file_exists($composerPath)) {
            $this->line("<comment>Skipped:</comment> composer.json (already exists)");

            return;
        }

        $stubPath = __DIR__ . '/stubs/module/composer.stub';

        if (! file_exists($stubPath)) {
            throw new Exception('Composer stub not found');
        }

        $content = file_get_contents($stubPath);
        $content = str_replace(
            ['{{module-name}}', '{{ModuleName}}'],
            [$this->moduleName, $this->moduleNameStudly],
            $content,
        );

        $this->filesystem->put($composerPath, $content);
        $this->line('<info>✓ Created:</info> composer.json');
    }

    private function updateMainComposerJson(): void
    {
        $originalWorkingDir = getcwd();
        chdir(base_path());

        $jsonFile = new JsonFile(Factory::getComposerFile());
        $definition = $jsonFile->read();

        if (! isset($definition['require'])) {
            $definition['require'] = [];
        }

        $composerName = "noerd/{$this->moduleName}";

        if (! isset($definition['require'][$composerName])) {
            $definition['require'][$composerName] = '*';
            $definition['require'] = $this->sortComposerPackages($definition['require']);

            $jsonFile->write($definition);
            $this->line("<info>✓ Updated:</info> composer.json (added {$composerName}:*)");
        } else {
            $this->line("<comment>Skipped:</comment> composer.json ({$composerName} already exists)");
        }

        chdir($originalWorkingDir);
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
