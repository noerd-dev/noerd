<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Noerd\Commands\Concerns\GeneratesResourceFiles;
use Noerd\Models\TenantApp;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

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
     * noerd:make-app stores as the app's main route. The same name in the
     * project and in a module (noerd:make-module): `{app}`.
     */
    public static function routeNameFor(string $app): string
    {
        return Str::lower($app);
    }

    /**
     * Every name a dashboard route of the app has had — `{app}` and the earlier
     * project name `{app}.dashboard` — so an existing one is found under either.
     *
     * @return list<string>
     */
    public static function routeNamesFor(string $app): array
    {
        return [self::routeNameFor($app), Str::lower($app) . '.dashboard'];
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

    /**
     * The name the app's dashboard route already carries (declared in the
     * route file or registered), or null when it has none yet.
     */
    protected function existingDashboardRoute(): ?string
    {
        $content = $this->routeFileContent();

        foreach (self::routeNamesFor($this->appConfigName) as $name) {
            if (preg_match('/->name\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*\)/', $content) === 1 || Route::has($name)) {
                return $name;
            }
        }

        return null;
    }

    protected function createDashboardBlade(): string
    {
        $path = "{$this->bladeBasePath()}/{$this->appConfigName}-dashboard.blade.php";

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
        $existing = $this->existingDashboardRoute();

        if ($existing !== null) {
            $this->warn("Dashboard route '{$existing}' already exists — skipping.");

            return;
        }

        $routeName = self::routeNameFor($this->appConfigName);

        $route = "Route::livewire('{$this->appConfigName}', '{$this->componentPrefix}{$this->appConfigName}-dashboard')->name('{$routeName}');";

        if (! $this->confirmStep("Add dashboard route to {$this->routeFileDisplay()}?\n  <comment>{$route}</comment>")) {
            return;
        }

        $this->appendRoute($route);
    }

    /**
     * Link the dashboard from every navigation copy (module template + installed
     * project copy for a module app, the project copy otherwise).
     */
    protected function addDashboardNavigation(): void
    {
        $routeName = $this->existingDashboardRoute() ?? self::routeNameFor($this->appConfigName);

        foreach ($this->yamlBasePaths() as $base) {
            $navPath = "{$base}/navigation.yml";

            if (! $this->filesystem->exists($navPath)) {
                $this->createDashboardNavigation($navPath, $routeName);

                continue;
            }

            $content = $this->filesystem->get($navPath);

            if ($this->navigationLinksDashboard($content)) {
                $this->warn("Dashboard navigation entry already exists in {$navPath} — skipping.");

                continue;
            }

            $entry = ['title' => 'Dashboard', 'route' => $routeName, 'heroicon' => 'home'];
            $navigation = Yaml::parse($content);

            if (! is_array($navigation) || ! isset($navigation[0]) || ! is_array($navigation[0])) {
                $this->warn("Navigation file has no app block: {$navPath} — skipping.");

                continue;
            }

            if (! $this->confirmStep("Add dashboard navigation entry to {$navPath}?\n<comment>" . Yaml::dump([$entry], 10, 2) . '</comment>')) {
                continue;
            }

            // The dashboard leads the first block — the entry the app opens with.
            $blocks = is_array($navigation[0]['blockMenus'] ?? null) ? $navigation[0]['blockMenus'] : [];
            if ($blocks === []) {
                $blocks[] = ['title' => 'Overview', 'navigations' => []];
            }
            $first = array_key_first($blocks);
            $blocks[$first]['navigations'] = [$entry, ...(array) ($blocks[$first]['navigations'] ?? [])];
            $navigation[0]['blockMenus'] = $blocks;

            $this->filesystem->put($navPath, Yaml::dump($navigation, 10, 2));
            $this->line("<info>Dashboard navigation added to:</info> {$navPath}");
        }
    }

    /**
     * Whether an entry of the navigation already opens the dashboard — under
     * either of its names (the app's own `route:` is not an entry).
     */
    protected function navigationLinksDashboard(string $content): bool
    {
        try {
            $navigation = Yaml::parse($content);
        } catch (ParseException) {
            return false;
        }

        foreach ((array) (is_array($navigation) ? ($navigation[0]['blockMenus'] ?? []) : []) as $block) {
            foreach ((array) (is_array($block) ? ($block['navigations'] ?? []) : []) as $entry) {
                if (is_array($entry) && in_array($entry['route'] ?? null, self::routeNamesFor($this->appConfigName), true)) {
                    return true;
                }
            }
        }

        return false;
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
            . "  blockMenus:\n"
            . "    - title: Overview\n"
            . "      navigations:\n"
            . "        - title: Dashboard\n"
            . "          route: {$routeName}\n"
            . "          heroicon: home\n";

        if (! $this->confirmStep("Create {$navPath} with a dashboard entry?\n<comment>{$navigation}</comment>")) {
            return;
        }

        $this->filesystem->ensureDirectoryExists(dirname($navPath));
        $this->filesystem->put($navPath, $navigation);
        $this->line("<info>Created:</info> {$navPath}");
    }

    /**
     * Confirm a scaffolding step, defaulting to yes. A non-interactive run (e.g. the
     * call from noerd:make-app) never asks: the child command shares the caller's
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
