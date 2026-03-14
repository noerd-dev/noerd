<?php

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Noerd\Commands\Concerns\GeneratesResourceFiles;

class MakeDashboardCommand extends Command
{
    use GeneratesResourceFiles;

    protected $signature = 'noerd:make-dashboard {--app= : App name (e.g. crm)}';

    protected $description = 'Generate a dashboard Blade file for an app';

    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    public function handle(): int
    {
        $result = $this->selectApp($this->option('app'));
        if ($result !== 0) {
            return $result;
        }

        try {
            $this->createDashboardBlade();

            $this->addDashboardRoute();

            $this->addDashboardNavigation();

            $this->line('');
            $this->info('Dashboard files created successfully!');

            return 0;
        } catch (Exception $e) {
            $this->error('Error creating dashboard: ' . $e->getMessage());

            return 1;
        }
    }

    protected function createDashboardBlade(): string
    {
        $bladeBase = base_path('resources/views/components');
        $path = "{$bladeBase}/{$this->appConfigName}-dashboard.blade.php";

        if ($this->checkFileExists($path)) {
            return '';
        }

        $stubPath = $this->getStubPath() . '/dashboard.blade.stub';

        if (! $this->filesystem->exists($stubPath)) {
            throw new Exception('Stub not found: dashboard.blade.stub');
        }

        $content = $this->filesystem->get($stubPath);
        $content = str_replace('{{appName}}', $this->appConfigName, $content);

        $this->filesystem->ensureDirectoryExists(dirname($path));
        $this->filesystem->put($path, $content);
        $this->line("<info>Created:</info> {$path}");

        return $path;
    }

    protected function addDashboardRoute(): void
    {
        $routeFile = base_path('routes/web.php');
        $content = $this->filesystem->get($routeFile);

        $routeName = "{$this->appConfigName}.dashboard";

        if (str_contains($content, "'{$routeName}'")) {
            $this->warn("Route '{$routeName}' already exists in routes/web.php — skipping.");

            return;
        }

        $route = "Route::livewire('{$this->appConfigName}', '{$this->appConfigName}-dashboard')->name('{$routeName}');";

        if (! $this->confirm("Add dashboard route to routes/web.php?\n  <comment>{$route}</comment>", true)) {
            return;
        }

        $this->filesystem->append($routeFile, "\n{$route}\n");
        $this->line("<info>Route added:</info> {$route}");
    }

    protected function addDashboardNavigation(): void
    {
        $navPath = base_path("app-configs/{$this->appConfigName}/navigation.yml");

        if (! $this->filesystem->exists($navPath)) {
            $this->warn("Navigation file not found: {$navPath} — skipping.");

            return;
        }

        $content = $this->filesystem->get($navPath);

        if (str_contains($content, "route: {$this->appConfigName}.dashboard")) {
            $this->warn("Dashboard navigation entry already exists in {$navPath} — skipping.");

            return;
        }

        $navEntry = "    - title: Dashboard\n"
            . "      route: {$this->appConfigName}.dashboard\n"
            . "      heroicon: home";

        if (! $this->confirm("Add dashboard navigation entry to {$this->appConfigName} navigation.yml?\n<comment>{$navEntry}</comment>", true)) {
            return;
        }

        $blockMenusPos = mb_strpos($content, 'block_menus:');
        if ($blockMenusPos !== false) {
            $afterBlockMenus = mb_strpos($content, "\n", $blockMenusPos);
            if ($afterBlockMenus !== false) {
                $newContent = mb_substr($content, 0, $afterBlockMenus + 1)
                    . $navEntry . "\n"
                    . mb_substr($content, $afterBlockMenus + 1);
                $this->filesystem->put($navPath, $newContent);
                $this->line("<info>Dashboard navigation added to:</info> {$navPath}");

                return;
            }
        }

        $content = mb_rtrim($content) . "\n" . $navEntry . "\n";
        $this->filesystem->put($navPath, $content);
        $this->line("<info>Dashboard navigation added to:</info> {$navPath}");
    }
}
