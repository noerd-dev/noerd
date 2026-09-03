<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\confirm;

use Laravel\Prompts\Elements\Link;
use Laravel\Prompts\Elements\NumberedList;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

use Noerd\Commands\Concerns\AsksForHeroicon;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Symfony\Component\Process\Process as SymfonyProcess;

class MakeAppCommand extends Command
{
    use AsksForHeroicon;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:make-app
                            {--title= : The display title of the app}
                            {--name= : The unique name identifier of the app}
                            {--icon= : The icon identifier for the app}
                            {--route= : An existing route to open instead of the generated dashboard}
                            {--active=1 : Whether the app is active (1 or 0)}
                            {--module : Scaffold the app as a module in app-modules/{app} instead of the project root}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new app with its own dashboard that can be assigned to tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $title = $this->option('title');
        $name = $this->option('name');
        $icon = $this->option('icon');
        $route = $this->option('route');
        $active = (bool) $this->option('active');

        // A scripted call passes every option (an empty value is a validation
        // error, not a prompt); only an omitted option is asked for interactively.
        $scripted = $title !== null && $name !== null && $icon !== null;
        $interactive = ! $scripted && $this->input->isInteractive();
        $asModule = (bool) $this->option('module');

        if ($interactive) {
            $this->info('Creating a new tenant app...');
            $this->newLine();

            if (! $asModule) {
                $asModule = select(
                    label: 'Where should the app be created?',
                    options: [
                        'root' => 'Project (resources/views, routes/web.php, app-configs/{app})',
                        'module' => 'Module (app-modules/{app}, own Composer package)',
                    ],
                    default: 'root',
                ) === 'module';
            }

            $title ??= text(label: 'App Title (display name, e.g. Inventory Management)', placeholder: 'Inventory Management', required: true);
            $name ??= $this->normalizeAppName(text(label: 'App Name (unique identifier, e.g. INVENTORY, CMS)', placeholder: 'INVENTORY', required: true));
            $icon ??= $this->askForHeroicon();
        }

        // Normalize name if provided via option
        if ($name) {
            $name = $this->normalizeAppName($name);
        }

        // Validate required fields
        if (! $title || ! $name || ! $icon) {
            $this->error('All fields (title, name, icon) are required.');
            return self::FAILURE;
        }

        // Validate name format (uppercase, no spaces) after normalization
        if (! preg_match('/^[A-Z_]+$/', $name)) {
            $this->error('App name must contain only uppercase letters and underscores (e.g., CMS, MEDIA, MY_APP).');
            return self::FAILURE;
        }

        if ($asModule) {
            return $this->createAsModule($title, $name, $icon, $interactive);
        }

        // Check if app with this name already exists
        if (TenantApp::where('name', $name)->exists()) {
            $this->error("App with name '{$name}' already exists.");
            return self::FAILURE;
        }

        // Every app ships its own dashboard: the app tile opens the generated
        // dashboard route unless the caller points it at an existing route.
        $generateDashboard = ! $route;
        $route = $route ?: MakeDashboardCommand::routeNameFor($name);

        // Create the tenant app
        try {
            $tenantApp = TenantApp::create([
                'title' => $title,
                'name' => $name,
                'icon' => $icon,
                'route' => $route,
                'is_active' => $active,
            ]);

            if ($generateDashboard) {
                $this->newLine();
                $this->call('noerd:make-dashboard', [
                    '--app' => Str::lower($name),
                    '--no-interaction' => true,
                ]);
            }

            $this->newLine();
            $this->info('Tenant app created successfully!');

            $this->table(['Field', 'Value'], [
                ['ID', $tenantApp->id],
                ['Title', $tenantApp->title],
                ['Name', $tenantApp->name],
                ['Icon', $tenantApp->icon],
                ['Route', $tenantApp->route],
                ['Active', $tenantApp->is_active ? 'Yes' : 'No'],
                ['Created', $tenantApp->created_at->format('Y-m-d H:i:s')],
            ]);

            if ($interactive) {
                $this->askToAssignTenants($tenantApp);
            } else {
                $this->newLine();
                $this->comment('Run "php artisan noerd:assign-apps-to-tenant" to assign this app to a tenant.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to create tenant app: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Module mode: the app becomes a Composer package under app-modules/{key} with
     * the module boilerplate (noerd:make-module) — dashboard, routes, navigation,
     * install/update commands; resources are added later with noerd:make-resource.
     * The tenant_apps row is NOT written here — the generated noerd:install-{key}
     * command registers the app (and stays re-runnable), exactly like every shipped
     * module.
     */
    protected function createAsModule(string $title, string $name, string $icon, bool $interactive): int
    {
        if ($this->option('route')) {
            $this->error('The --route option cannot be combined with --module: a module ships its own dashboard route.');

            return self::FAILURE;
        }

        if (! (bool) $this->option('active')) {
            $this->comment('Note: --active is ignored in module mode; the install command registers the app as active.');
        }

        // MY_APP → module key my-app → tenant app MY-APP (HasModuleInstallation::deriveAppKey()).
        $moduleKey = Str::lower(str_replace('_', '-', $name));
        $appKey = Str::upper($moduleKey);
        $modulePath = base_path("app-modules/{$moduleKey}");

        if (is_dir($modulePath)) {
            $this->error("Module directory already exists: app-modules/{$moduleKey}");

            return self::FAILURE;
        }

        if (TenantApp::where('name', $appKey)->exists()) {
            $this->error("App with name '{$appKey}' already exists.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("<comment>Module:</comment> app-modules/{$moduleKey}");
        $this->line("<comment>App key:</comment> {$appKey}");
        $this->newLine();

        $result = $this->call('noerd:make-module', [
            'name' => $moduleKey,
            '--title' => $title,
            '--icon' => $icon,
            '--no-hints' => $interactive,
            '--no-interaction' => true,
        ]);

        if ($result !== self::SUCCESS) {
            return self::FAILURE;
        }

        if (! $interactive) {
            return self::SUCCESS;
        }

        return $this->installModule($moduleKey, $title) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The steps after the scaffold run on their own: Composer registers the package
     * (quietly), then the generated install command runs in silent scaffold mode —
     * the only question left is which tenants get the app. The install command only
     * exists once Composer knows the package, so both run as child processes; the
     * install gets the terminal for its tenant selection where one is available.
     */
    protected function installModule(string $moduleKey, string $title): bool
    {
        $this->newLine();
        $this->line('<comment>Registering the module with Composer...</comment>');

        if (! $this->runChildProcess(['composer', 'update', "noerd/{$moduleKey}", '--no-interaction', '--quiet'], stream: false)) {
            $this->error("composer update failed. Run it manually, then: php artisan noerd:install-{$moduleKey}");

            return false;
        }

        $this->line('<comment>Installing the app...</comment>');
        $this->newLine();

        $install = [PHP_BINARY, 'artisan', "noerd:install-{$moduleKey}", '--scaffold'];
        if (! SymfonyProcess::isTtySupported()) {
            // No terminal for the tenant selection: the app goes to every tenant.
            $install[] = '--no-interaction';
        }

        if (! $this->runChildProcess($install)) {
            $this->error("The install command failed. Run it manually: php artisan noerd:install-{$moduleKey}");

            return false;
        }

        $this->displayAppReady($moduleKey, $title);

        return true;
    }

    /**
     * The closing callout, in the style of noerd:install.
     */
    protected function displayAppReady(string $moduleKey, string $title): void
    {
        $appsUrl = mb_rtrim((string) config('app.url'), '/') . '/noerd-apps';
        $makeResource = "php artisan noerd:make-resource {Model} --app={$moduleKey}";

        callout("{$title} is ready", [
            "The module lives in app-modules/{$moduleKey} and is installed for the selected tenants.",
            new NumberedList([
                'Open: ' . new Link($appsUrl) . ' and pick the app',
                "Add a record type: create the model + migration in the module, then {$makeResource}",
            ]),
            'How modules work: ' . new Link('https://noerd.dev', 'documentation') . '.',
        ]);
    }

    /**
     * The command is passed as an argument list, never as a shell string: the PHP
     * binary may live in a path with spaces (e.g. Herd's "Application Support").
     *
     * @param  array<int, string>  $command
     */
    protected function runChildProcess(array $command, bool $stream = true): bool
    {
        $process = Process::path(base_path())->timeout(600);

        if (! $stream) {
            $result = $process->run($command);

            if (! $result->successful()) {
                $this->output->write($result->errorOutput());
            }

            return $result->successful();
        }

        $result = $process
            ->tty(SymfonyProcess::isTtySupported())
            ->run($command, function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

        return $result->successful();
    }

    protected function normalizeAppName(string $name): string
    {
        // Replace spaces and hyphens with underscores and convert to uppercase
        return mb_strtoupper((string) preg_replace('/[\s-]+/', '_', mb_trim($name)));
    }

    protected function askToAssignTenants(TenantApp $tenantApp): void
    {
        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->newLine();
            $this->comment('No tenants found to assign.');
            return;
        }

        $this->newLine();

        if (! confirm('Would you like to assign this app to tenants?', default: true)) {
            $this->comment('Run "php artisan noerd:assign-apps-to-tenant" later to assign.');
            return;
        }

        $tenantChoices = [];
        $allTenantIds = [];
        foreach ($tenants as $tenant) {
            $tenantChoices[$tenant->id] = $tenant->name;
            $allTenantIds[] = $tenant->id;
        }

        $selectedTenantIds = multiselect(
            label: 'Select tenants to assign this app to:',
            options: $tenantChoices,
            default: $allTenantIds,
            scroll: 10,
            required: false,
        );

        if (! empty($selectedTenantIds)) {
            $tenantApp->tenants()->sync($selectedTenantIds);
            $this->info('App assigned to ' . count($selectedTenantIds) . ' tenant(s).');
        } else {
            $this->comment('No tenants selected.');
        }
    }
}
