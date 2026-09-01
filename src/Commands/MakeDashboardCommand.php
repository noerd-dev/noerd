<?php

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Noerd\Commands\Concerns\GeneratesResourceFiles;
use Noerd\Models\TenantApp;

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

    /**
     * The route name of the dashboard generated for the given app — the target
     * noerd:create-app stores as the app's main route.
     */
    public static function routeNameFor(string $app): string
    {
        return Str::lower($app) . '.dashboard';
    }

    public function handle(): int
    {
        $result = $this->selectApp($this->option('app'));
        if ($result !== self::SUCCESS) {
            return $result;
        }

        try {
            $this->createDashboardBlade();

            $this->addDashboardRoute();

            $this->addDashboardNavigation();

            $this->line('');
            $this->info('Dashboard files created successfully!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error creating dashboard: ' . $e->getMessage());

            return self::FAILURE;
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

        if (! $this->filesystem->exists($routeFile)) {
            $this->filesystem->ensureDirectoryExists(dirname($routeFile));
            $this->filesystem->put($routeFile, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        }

        $content = $this->filesystem->get($routeFile);

        $routeName = self::routeNameFor($this->appConfigName);

        if (str_contains($content, "'{$routeName}'")) {
            $this->warn("Route '{$routeName}' already exists in routes/web.php — skipping.");

            return;
        }

        $route = "Route::livewire('{$this->appConfigName}', '{$this->appConfigName}-dashboard')->name('{$routeName}');";

        if (! $this->confirmStep("Add dashboard route to routes/web.php?\n  <comment>{$route}</comment>")) {
            return;
        }

        $this->appendRoute($route);
    }

    protected function addDashboardNavigation(): void
    {
        $navPath = base_path("app-configs/{$this->appConfigName}/navigation.yml");
        $routeName = self::routeNameFor($this->appConfigName);

        if (! $this->filesystem->exists($navPath)) {
            $this->createDashboardNavigation($navPath, $routeName);

            return;
        }

        $content = $this->filesystem->get($navPath);

        if (str_contains($content, "route: {$routeName}")) {
            $this->warn("Dashboard navigation entry already exists in {$navPath} — skipping.");

            return;
        }

        $navEntry = "    - title: Dashboard\n"
            . "      route: {$routeName}\n"
            . "      heroicon: home";

        if (! $this->confirmStep("Add dashboard navigation entry to {$this->appConfigName} navigation.yml?\n<comment>{$navEntry}</comment>")) {
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

    /**
     * A freshly created app has no navigation yet: write a minimal navigation.yml
     * whose first block lists the dashboard, so the app is usable right away and
     * noerd:make-resource / noerd:make-page can append their entries to that block.
     */
    protected function createDashboardNavigation(string $navPath, string $routeName): void
    {
        $title = TenantApp::query()
            ->where('name', mb_strtoupper($this->appConfigName))
            ->value('title') ?: Str::headline($this->appConfigName);

        $navigation = "- title: '" . str_replace("'", "''", $title) . "'\n"
            . "  name: {$this->appConfigName}\n"
            . "  route: {$routeName}\n"
            . "  block_menus:\n"
            . "    - title: Overview\n"
            . "      navigations:\n"
            . "        - title: Dashboard\n"
            . "          route: {$routeName}\n"
            . "          heroicon: home\n";

        if (! $this->confirmStep("Create {$this->appConfigName} navigation.yml with a dashboard entry?\n<comment>{$navigation}</comment>")) {
            return;
        }

        $this->filesystem->ensureDirectoryExists(dirname($navPath));
        $this->filesystem->put($navPath, $navigation);
        $this->line("<info>Created:</info> {$navPath}");
    }

    /**
     * Confirm a scaffolding step, defaulting to yes. A non-interactive run (e.g. the
     * call from noerd:create-app) never asks: the child command shares the caller's
     * output style, so the question would otherwise reach the caller's prompt.
     */
    protected function confirmStep(string $question): bool
    {
        if (! $this->input->isInteractive()) {
            return true;
        }

        return $this->confirm($question, true);
    }
}
