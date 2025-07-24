<?php

namespace Nywerk\Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'noerd:install
                            {--clean : Use the clean version without Tailwind header comment}
                            {--force : Overwrite existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Install Noerd UI CSS file to public/css directory';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing Noerd UI CSS...');

        // Determine source file based on options
        $useClean = $this->option('clean');
        $sourceFileName = $useClean ? 'noerd-clean.css' : 'noerd.css';
        
        // Define paths
        $sourceFile = __DIR__ . '/../../dist/' . $sourceFileName;
        $targetDir = public_path('css');
        $targetFile = $targetDir . '/noerd.css';

        // Check if source file exists
        if (!File::exists($sourceFile)) {
            $this->error("Source file not found: {$sourceFile}");
            $this->info('Please run "npm run build-css" in the noerd module directory first.');
            return self::FAILURE;
        }

        // Create target directory if it doesn't exist
        if (!File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
            $this->info("Created directory: {$targetDir}");
        }

        // Check if target file exists and handle accordingly
        if (File::exists($targetFile) && !$this->option('force')) {
            if (!$this->confirm("File {$targetFile} already exists. Overwrite?")) {
                $this->info('Installation cancelled.');
                return self::SUCCESS;
            }
        }

        // Copy the file
        try {
            File::copy($sourceFile, $targetFile);
            
            $version = $useClean ? 'clean version (without Tailwind header)' : 'standard version';
            $this->info("✅ Successfully installed Noerd UI CSS ({$version})");
            $this->line("   Source: {$sourceFile}");
            $this->line("   Target: {$targetFile}");
            
            // Show usage instructions
            $this->newLine();
            $this->info('📝 Usage in your Blade templates:');
            $this->line('<link rel="stylesheet" href="{{ asset(\'css/noerd.css\') }}">');
            
            $this->newLine();
            $this->info('🎨 Available CSS classes: noerd-input, noerd-button-primary, noerd-nav-link, etc.');
            $this->info('📖 Documentation: app-modules/noerd/README.md');
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to copy file: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
} 