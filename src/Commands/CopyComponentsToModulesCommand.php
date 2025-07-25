<?php

namespace Noerd\Noerd\Commands;

use Illuminate\Console\Command;
use Noerd\Noerd\Helpers\StaticConfigHelper;

class CopyComponentsToModulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:copy-components-to-modules {--dry-run : Show what would be copied without actually copying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy global components to their respective app-modules';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Copying components to app-modules...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No files will actually be copied');
        }

        try {
            if ($this->option('dry-run')) {
                $results = $this->simulateCopyComponents();
            } else {
                $results = StaticConfigHelper::copyComponentsToModules();
            }

            $this->displayResults($results);

            if (!$this->option('dry-run')) {
                $this->info('Components successfully copied to app-modules!');
            }

        } catch (\Exception $e) {
            $this->error('Error copying components: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Simulate copying components (for dry-run mode)
     */
    private function simulateCopyComponents(): array
    {
        $results = [];
        $componentMapping = $this->getComponentToModuleMapping();

        // Simulate default components
        $defaultComponentsPath = base_path('content/components/default');
        if (is_dir($defaultComponentsPath)) {
            $results['default'] = $this->simulateComponentsFromDirectory($defaultComponentsPath, $componentMapping, 'default');
        }

        // Simulate admin components
        $adminComponentsPath = base_path('content/components/admin');
        if (is_dir($adminComponentsPath)) {
            $results['admin'] = $this->simulateComponentsFromDirectory($adminComponentsPath, $componentMapping, 'admin');
        }

        return $results;
    }

    /**
     * Simulate copying components from a specific directory
     */
    private function simulateComponentsFromDirectory(string $sourceDir, array $componentMapping, string $userGroup): array
    {
        $results = [];
        $files = glob($sourceDir . '/*.yml');

        foreach ($files as $file) {
            $componentName = basename($file, '.yml');
            $module = $componentMapping[$componentName] ?? null;

            if ($module) {
                $targetDir = base_path("app-modules/{$module}/content/components");
                $targetFile = $targetDir . "/{$componentName}.yml";

                // Check if module directory exists
                $moduleExists = is_dir(base_path("app-modules/{$module}"));

                $results[] = [
                    'component' => $componentName,
                    'module' => $module,
                    'userGroup' => $userGroup,
                    'success' => $moduleExists,
                    'target' => $targetFile,
                    'exists' => file_exists($targetFile),
                    'moduleExists' => $moduleExists
                ];
            } else {
                $results[] = [
                    'component' => $componentName,
                    'module' => 'unknown',
                    'userGroup' => $userGroup,
                    'success' => false,
                    'reason' => 'No module mapping found'
                ];
            }
        }

        return $results;
    }

    /**
     * Display the results in a formatted table
     */
    private function displayResults(array $results): void
    {
        foreach ($results as $userGroup => $groupResults) {
            $this->line('');
            $this->info("Results for {$userGroup} components:");

            $tableData = [];
            $successCount = 0;
            $totalCount = 0;

            foreach ($groupResults as $result) {
                $totalCount++;

                $status = $result['success'] ?
                    '<info>✓</info>' :
                    '<error>✗</error>';

                if ($result['success']) {
                    $successCount++;
                    $note = '';

                    if ($this->option('dry-run')) {
                        if (isset($result['exists']) && $result['exists']) {
                            $note = ' (would overwrite)';
                            $status = '<comment>⚠</comment>';
                        }
                        if (!isset($result['moduleExists']) || !$result['moduleExists']) {
                            $note = ' (module not found)';
                            $status = '<error>✗</error>';
                        }
                    }

                    $tableData[] = [
                        $result['component'],
                        $result['module'],
                        $status,
                        ($result['target'] ?? '') . $note
                    ];
                } else {
                    $tableData[] = [
                        $result['component'],
                        $result['module'],
                        $status,
                        $result['reason'] ?? ''
                    ];
                }
            }

            $this->table(
                ['Component', 'Module', 'Status', $this->option('dry-run') ? 'Target/Note' : 'Note'],
                $tableData
            );

            $this->line("Successful: {$successCount}/{$totalCount}");
        }
    }

    /**
     * Get the component to module mapping (same as in StaticConfigHelper)
     */
    private function getComponentToModuleMapping(): array
    {
        return [
            // Product related
            'product' => 'product',
            'product-group' => 'product',

            // Customer related
            'customer' => 'customer',

            // Delivery/Liefertool related
            'deliverySlot' => 'liefertool',
            'deliveryBlock' => 'liefertool',
            'deliverytime' => 'liefertool',
            'deliveryarea' => 'liefertool',
            'vehicle-component' => 'liefertool',
            'vehicle-configuration-component' => 'liefertool',
            'vehicleAssembly' => 'liefertool',
            'area-component' => 'liefertool',

            // Order related
            'orderConfirmation' => 'order',

            // Voucher related
            'voucher' => 'voucher',

            // Shop related
            'shop-notification' => 'shop',
            'store' => 'shop',

            // Menu/Canteen related
            'menu' => 'canteen',

            // Content/CMS related
            'page' => 'content',
            'site' => 'content',
            'text-content-component' => 'content',
            'textDocument' => 'content',

            // Legal register related
            'law' => 'legal-register',
            'lawReadOnly' => 'legal-register',
            'duty' => 'legal-register',
            'dutyReadOnly' => 'legal-register',

            // Production planning related
            'assembly-component' => 'production-planning',
            'part-component' => 'production-planning',
            'selectPart' => 'production-planning',

            // Harvester/PDM related
            'project' => 'harvester-project',
            'project-booking' => 'harvester-project',
            'sawmill' => 'pdm',

            // UKI related
            'mode' => 'uki',
            'mode-exception' => 'uki',
            'times' => 'uki',

            // Settings related
            'setting' => 'settings',
            'globalParameter' => 'settings',
            'tenant' => 'settings',
            'user' => 'settings',
            'userRole' => 'settings',

            // Document analyzer related
            'ocr-scanner-component' => 'document-analyzer',

            // Media related
            'prompt' => 'media',
            'promptCreate' => 'media',

            // Additional fields - could be used by multiple modules, default to content
            'additionalField' => 'content',

            // Accounting related
            'accounting' => 'accounting',
        ];
    }
}
