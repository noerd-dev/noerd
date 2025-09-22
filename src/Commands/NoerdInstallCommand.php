<?php

namespace Noerd\Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NoerdInstallCommand extends Command
{
    protected $signature = 'noerd:install {--force : Overwrite existing files without asking}';

    protected $description = 'Install noerd content to the local content directory';

    public function handle()
    {
        $this->info('Installing noerd content...');

        $sourceDir = base_path('vendor/noerd/noerd/content');
        $targetDir = base_path('content');

        if (!is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");
            return 1;
        }

        // Create target directory if it doesn't exist
        if (!is_dir($targetDir)) {

            if (!mkdir($targetDir, 0755, true)) {
                $this->error("Failed to create target directory: {$targetDir}");
                return 1;
            }

            $this->info("Created target directory: {$targetDir}");
        }

        try {
            $results = $this->copyDirectoryContents($sourceDir, $targetDir);

            // Ensure lists are copied explicitly to content/lists
            $listsSource = $sourceDir . DIRECTORY_SEPARATOR . 'lists';
            $listsTarget = $targetDir . DIRECTORY_SEPARATOR . 'lists';
            if (is_dir($listsSource)) {
                $listResults = $this->copyDirectoryContents($listsSource, $listsTarget);
                $results = $this->mergeResults($results, $listResults);
            }

            $this->displaySummary($results);

            // Ensure app-modules directory exists
            $this->ensureAppModulesDirectory();

            // Setup frontend assets and configuration
            $this->setupFrontendAssets();

            $this->info('Noerd content successfully installed!');

            return 0;
        } catch (Exception $e) {
            $this->error('Error installing noerd content: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Copy directory contents recursively
     */
    private function copyDirectoryContents(string $sourceDir, string $targetDir): array
    {
        $results = [
            'created_dirs' => 0,
            'copied_files' => 0,
            'skipped_files' => 0,
            'overwritten_files' => 0,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = mb_substr($sourcePath, mb_strlen($sourceDir) + 1);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                // Create directory if it doesn't exist
                if (!is_dir($targetPath)) {

                    if (!mkdir($targetPath, 0755, true)) {
                        throw new Exception("Failed to create directory: {$targetPath}");
                    }

                    $this->line("<info>Created directory:</info> {$relativePath}");
                    $results['created_dirs']++;
                }
            } else {
                // Check if file already exists
                if (file_exists($targetPath)) {
                    if (!$this->option('force')) {


                        $choice = $this->choice(
                            "File already exists: {$relativePath}. What do you want to do?",
                            ['skip', 'overwrite', 'overwrite-all'],
                            'skip',
                        );

                        if ($choice === 'skip') {
                            $this->line("<comment>Skipped:</comment> {$relativePath}");
                            $results['skipped_files']++;
                            continue;
                        }
                        if ($choice === 'overwrite-all') {
                            // Set force option for remaining files
                            $this->input->setOption('force', true);
                        }
                    }

                    $this->line("<comment>Overwriting:</comment> {$relativePath}");
                    $results['overwritten_files']++;
                } else {
                    $this->line("<info>Copying:</info> {$relativePath}");
                    $results['copied_files']++;
                }

                if (!copy($sourcePath, $targetPath)) {
                    throw new Exception("Failed to copy file: {$sourcePath} to {$targetPath}");
                }

            }
        }

        return $results;
    }

    private function mergeResults(array $a, array $b): array
    {
        foreach (['created_dirs', 'copied_files', 'skipped_files', 'overwritten_files'] as $key) {
            $a[$key] = ($a[$key] ?? 0) + ($b[$key] ?? 0);
        }
        return $a;
    }

    /**
     * Setup frontend assets and configuration
     */
    private function setupFrontendAssets(): void
    {
        $this->line('');
        $this->info('Setting up frontend assets...');

        try {
            // Update app.css
            $this->updateAppCss();

            // Update app.js
            $this->updateAppJs();

            // Install npm packages
            $this->installNpmPackages();

            // Create tailwind.config.js
            $this->createTailwindConfig();

            // Update filesystems configuration
            $this->updateFilesystemsConfig();

            // Update auth configuration
            $this->updateAuthConfig();

            // Update composer.json repositories
            $this->updateComposerRepositories();

            // Create app-modules configuration
            $this->createAppModulesConfig();

            $this->line('<info>Frontend assets setup completed successfully.</info>');
        } catch (Exception $e) {
            $this->warn('Frontend assets setup failed: ' . $e->getMessage());
        }
    }

    /**
     * Update app.css with noerd styles
     */
    private function updateAppCss(): void
    {
        $cssPath = base_path('resources/css/app.css');

        if (!file_exists($cssPath)) {
            $this->warn('app.css not found, skipping CSS updates.');
            return;
        }

        $cssContent = file_get_contents($cssPath);

        $noerdStyles = "
@import 'quill/dist/quill.snow.css';
@source '../../vendor/livewire/flux-pro/stubs/**/*.blade.php';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';
@source '../../vendor/noerd/noerd/resources/views/**/*.blade.php';
@source '../../vendor/noerd/cms/resources/views/**/*.blade.php';
@config '../../tailwind.config.js';

@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';

    --color-zinc-50: #fafafa;
    --color-zinc-100: #f5f5f5;
    --color-zinc-200: #e5e5e5;
    --color-zinc-300: #d4d4d4;
    --color-zinc-400: #a3a3a3;
    --color-zinc-500: #737373;
    --color-zinc-600: #525252;
    --color-zinc-700: #404040;
    --color-zinc-800: #262626;
    --color-zinc-900: #171717;
    --color-zinc-950: #0a0a0a;

    --color-accent: var(--color-neutral-800);
    --color-accent-content: var(--color-neutral-800);
    --color-accent-foreground: var(--color-white);
}

@layer theme {
    .dark {
        --color-accent: var(--color-white);
        --color-accent-content: var(--color-white);
        --color-accent-foreground: var(--color-neutral-800);
    }
}

@layer base {

    *,
    ::after,
    ::before,
    ::backdrop,
    ::file-selector-button {
        border-color: var(--color-gray-200, currentColor);
    }
}

[data-flux-field] {
    @apply grid gap-2;
}

[data-flux-label] {
    @apply  !mb-0 !leading-tight;
}

input:focus[data-flux-control],
textarea:focus[data-flux-control],
select:focus[data-flux-control] {
    @apply outline-hidden ring-2 ring-accent ring-offset-2 ring-offset-accent-foreground;
}
";

        // Check if noerd styles are already present
        if (!str_contains($cssContent, '@source \'../../vendor/noerd/noerd/resources/views/**/*.blade.php\';')) {
            file_put_contents($cssPath, $cssContent . $noerdStyles);
            $this->line('<info>Updated app.css with noerd styles.</info>');
        } else {
            $this->line('<comment>Noerd styles already present in app.css.</comment>');
        }
    }

    /**
     * Update app.js with Alpine.js configuration
     */
    private function updateAppJs(): void
    {
        $jsPath = base_path('resources/js/app.js');

        if (!file_exists($jsPath)) {
            $this->warn('app.js not found, skipping JS updates.');
            return;
        }

        $jsContent = file_get_contents($jsPath);

        $alpineConfig = "
import sort from '@alpinejs/sort'
import Quill from 'quill';

// Quill global verfügbar machen (falls nötig für andere Komponenten)
window.Quill = Quill;

Alpine.plugin(sort)

Alpine.store('globalState', {
    open: true,
});

Alpine.store('app', {
    currentId: 200,
    setId(id) {
        this.currentId = id;
    }
});
";

        // Check if Alpine sort plugin is already imported
        if (!str_contains($jsContent, "import sort from '@alpinejs/sort'")) {
            file_put_contents($jsPath, $jsContent . $alpineConfig);
            $this->line('<info>Updated app.js with Alpine.js configuration.</info>');
        } else {
            $this->line('<comment>Alpine.js configuration already present in app.js.</comment>');
        }
    }

    /**
     * Install required npm packages
     */
    private function installNpmPackages(): void
    {
        $this->line('<comment>Installing npm packages...</comment>');

        $packages = [
            '@tailwindcss/forms@^0.5.2',
            'tailwind-scrollbar@^4.0.2',
            'dotenv@^16.4.7',
            '@alpinejs/sort',
            'quill',
        ];

        $command = 'cd ' . base_path() . ' && npm install ' . implode(' ', $packages) . ' --save-dev';
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->warn('Failed to install npm packages. You may need to run the following manually:');
            $this->warn('npm install ' . implode(' ', $packages) . ' --save-dev');
        } else {
            $this->line('<info>NPM packages installed successfully.</info>');
        }
    }

    /**
     * Create tailwind.config.js
     */
    private function createTailwindConfig(): void
    {
        $configPath = base_path('tailwind.config.js');

        if (file_exists($configPath) && !$this->option('force')) {
            if (!$this->confirm('tailwind.config.js already exists. Do you want to overwrite it?')) {
                $this->line('<comment>Skipped tailwind.config.js creation.</comment>');
                return;
            }
        }

        $configContent = "import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'

require('dotenv').config();

/** @type {import('tailwindcss').Config} */
export default {
    content: [

    ],

    safelist: [

    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            display: ['group-hover'],
            colors: {
                'brand-highlight': process.env.VITE_PRIMARY_COLOR || '#000',
                'brand-bg': process.env.VITE_BG_COLOR || '#f9f9f9',
                'brand-navi': process.env.VITE_BRAND_NAVI || '#fafafa',
                'brand-navi-hover': process.env.VITE_BRAND_NAVI_HOVER || '#f5f5f5',
                'brand-primary': process.env.VITE_BRAND_PRIMARY || '#000',
            },
        },
    },

    plugins: [forms, require('tailwind-scrollbar')],
}
";

        file_put_contents($configPath, $configContent);
        $this->line('<info>Created tailwind.config.js.</info>');
    }

    /**
     * Update filesystems.php configuration
     */
    private function updateFilesystemsConfig(): void
    {
        $filesystemsPath = base_path('config/filesystems.php');

        if (!file_exists($filesystemsPath)) {
            $this->warn('filesystems.php not found, skipping filesystem configuration.');
            return;
        }

        $filesystemsContent = file_get_contents($filesystemsPath);

        // Check if media disk is already configured
        if (str_contains($filesystemsContent, "'media' =>")) {
            $this->line('<comment>Media disk already configured in filesystems.php.</comment>');
            return;
        }

        // Find the position to insert the media disk configuration
        // Look for the closing of the 'disks' array
        $pattern = '/(\s+)(],\s*\/\*[\s\S]*?Symbolic Links[\s\S]*?\*\/)/';

        $mediaDiskConfig = "
        'media' => [
            'driver' => 'local',
            'root' => storage_path('app/public/media'),
            'url' => env('APP_URL') . '/storage/media',
            'visibility' => 'public',
            'throw' => false,
        ],
";

        $replacement = $mediaDiskConfig . '$1$2';
        $updatedContent = preg_replace($pattern, $replacement, $filesystemsContent);

        if ($updatedContent && $updatedContent !== $filesystemsContent) {
            file_put_contents($filesystemsPath, $updatedContent);
            $this->line('<info>Added media disk configuration to filesystems.php.</info>');
        } else {
            $this->warn('Could not automatically add media disk configuration. Please add it manually.');
        }
    }

    /**
     * Update auth.php configuration to use Noerd User model
     */
    private function updateAuthConfig(): void
    {
        $authPath = base_path('config/auth.php');

        if (!file_exists($authPath)) {
            $this->warn('auth.php not found, skipping auth configuration.');
            return;
        }

        $authContent = file_get_contents($authPath);

        // Check if Noerd User model is already configured
        if (str_contains($authContent, 'Noerd\\Noerd\\Models\\User::class')) {
            $this->line('<comment>Noerd User model already configured in auth.php.</comment>');
            return;
        }

        // Replace App\Models\User::class with Noerd\Noerd\Models\User::class
        $patterns = [
            // Pattern for direct class reference
            '/App\\\\Models\\\\User::class/' => '\\Noerd\\Noerd\\Models\\User::class',
            // Pattern for env() with App\Models\User::class as default
            '/env\([\'"]AUTH_MODEL[\'"],\s*App\\\\Models\\\\User::class\)/' => 'env(\'AUTH_MODEL\', \\Noerd\\Noerd\\Models\\User::class)',
            // Pattern for just App\Models\User without ::class
            '/[\'"]?App\\\\Models\\\\User[\'"]?/' => '\\Noerd\\Noerd\\Models\\User::class',
        ];

        $updatedContent = $authContent;
        $hasChanges = false;

        foreach ($patterns as $pattern => $replacement) {
            $newContent = preg_replace($pattern, $replacement, $updatedContent);
            if ($newContent && $newContent !== $updatedContent) {
                $updatedContent = $newContent;
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            file_put_contents($authPath, $updatedContent);
            $this->line('<info>Updated auth.php to use Noerd User model.</info>');
        } else {
            $this->line('<comment>No changes needed in auth.php configuration.</comment>');
        }
    }

    /**
     * Display summary of operations
     */
    private function displaySummary(array $results): void
    {
        $this->line('');
        $this->info('Installation Summary:');
        $this->table(
            ['Operation', 'Count'],
            [
                ['Directories created', $results['created_dirs']],
                ['Files copied', $results['copied_files']],
                ['Files overwritten', $results['overwritten_files']],
                ['Files skipped', $results['skipped_files']],
            ],
        );
    }

    /**
     * Update composer.json to add repositories configuration
     */
    private function updateComposerRepositories(): void
    {
        $composerPath = base_path('composer.json');

        if (!file_exists($composerPath)) {
            $this->warn('composer.json not found, skipping repositories update');
            return;
        }

        $composerContent = file_get_contents($composerPath);
        $composerData = json_decode($composerContent, true);

        if (!$composerData) {
            $this->warn('Failed to parse composer.json, skipping repositories update');
            return;
        }

        // Check if repositories already exists
        if (isset($composerData['repositories'])) {
            // Check if our path repository already exists
            foreach ($composerData['repositories'] as $repo) {
                if (isset($repo['type']) && $repo['type'] === 'path'
                    && isset($repo['url']) && $repo['url'] === 'app-modules/*') {
                    $this->line('Repositories configuration already exists in composer.json');
                    return;
                }
            }
        } else {
            $composerData['repositories'] = [];
        }

        // Add the path repository
        $composerData['repositories'][] = [
            'type' => 'path',
            'url' => 'app-modules/*',
            'options' => [
                'symlink' => true,
            ],
        ];

        // Write back to composer.json with pretty formatting
        $newContent = json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($composerPath, $newContent) !== false) {
            $this->line('Added repositories configuration to composer.json');
        } else {
            $this->warn('Failed to update composer.json');
        }
    }

    /**
     * Create app-modules.php configuration with Noerd namespace
     */
    private function createAppModulesConfig(): void
    {
        $configPath = base_path('config/app-modules.php');

        if (file_exists($configPath)) {
            // Check if already has Noerd namespace
            $currentContent = file_get_contents($configPath);
            if (str_contains($currentContent, "'modules_namespace' => 'Noerd'")) {
                $this->line('<comment>app-modules.php already configured with Noerd namespace.</comment>');
                return;
            }

            if (!$this->option('force')) {
                if (!$this->confirm('app-modules.php already exists. Do you want to update it to use Noerd namespace?')) {
                    $this->line('<comment>Skipped app-modules.php configuration update.</comment>');
                    return;
                }
            }
        }

        $configContent = "<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modules Namespace
    |--------------------------------------------------------------------------
    |
    | This is the PHP namespace that your modules will be created in. For
    | example, a module called \"Helpers\" will be placed in \\Noerd\\Helpers
    | by default.
    |
    | It is *highly recommended* that you configure this to your organization
    | name to make extracting modules to their own package easier (should you
    | choose to ever do so).
    |
    | If you set the namespace, you should also set the vendor name to match.
    |
    */

    'modules_namespace' => 'Noerd',

    /*
    |--------------------------------------------------------------------------
    | Composer \"Vendor\" Name
    |--------------------------------------------------------------------------
    |
    | This is the prefix used for your composer.json file. This should be the
    | kebab-case version of your module namespace (if left null, we will
    | generate the kebab-case version for you).
    |
    */

    'modules_vendor' => null,

    /*
    |--------------------------------------------------------------------------
    | Modules Directory
    |--------------------------------------------------------------------------
    |
    | If you want to install modules in a custom directory, you can do so here.
    | Keeping the default `app-modules/` directory is highly recommended,
    | though, as it keeps your modules near the rest of your application code
    | in an alpha-sorted directory listing.
    |
    */

    'modules_directory' => 'app-modules',

    /*
    |--------------------------------------------------------------------------
    | Base Test Case
    |--------------------------------------------------------------------------
    |
    | This is the base TestCase class name that auto-generated Tests should
    | extend. By default it assumes the default \\Tests\\TestCase exists.
    |
    */

    'tests_base' => 'Tests\\TestCase',

    /*
    |--------------------------------------------------------------------------
    | Custom Stubs
    |--------------------------------------------------------------------------
    |
    | If you would like to use your own custom stubs for new modules, you can
    | configure those here. This should be an array where the key is the path
    | relative to the module and the value is the absolute path to the stub
    | stub file. Destination paths and contents support placeholders. See the
    | README.md file for more information.
    |
    | For example:
    |
    | 'stubs' => [
    | 	'src/Providers/StubClassNamePrefixServiceProvider.php' => base_path('stubs/app-modules/ServiceProvider.php'),
    | ],
    */

    'stubs' => null,

    /*
    |--------------------------------------------------------------------------
    | Custom override of event discovery
    |--------------------------------------------------------------------------
    |
    | This is a custom override of the event discovery feature. If you want to
    | disable event discovery, set this to false. If you want to enable event
    | discovery, set this to true. We will still check the app namespace for
    | the presence of event discovery.
    */

    'should_discover_events' => null,
];
";

        if (file_put_contents($configPath, $configContent) !== false) {
            $this->line('<info>Created/updated app-modules.php with Noerd namespace.</info>');
        } else {
            $this->warn('Failed to create/update app-modules.php configuration.');
        }
    }

    /**
     * Ensure app-modules directory exists with .gitkeep file
     */
    private function ensureAppModulesDirectory(): void
    {
        $appModulesPath = base_path('app-modules');

        if (!is_dir($appModulesPath)) {
            if (!mkdir($appModulesPath, 0755, true)) {
                $this->warn('Failed to create app-modules directory');
                return;
            }
            $this->line('Created app-modules directory');
        } else {
            $this->line('<comment>app-modules directory already exists</comment>');
        }

        $gitkeepPath = $appModulesPath . DIRECTORY_SEPARATOR . '.gitkeep';

        if (!file_exists($gitkeepPath)) {
            if (file_put_contents($gitkeepPath, '') !== false) {
                $this->line('Created .gitkeep file in app-modules directory');
            } else {
                $this->warn('Failed to create .gitkeep file');
            }
        } else {
            $this->line('<comment>.gitkeep already exists in app-modules directory</comment>');
        }
    }
}
