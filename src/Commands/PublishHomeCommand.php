<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishHomeCommand extends Command
{
    protected $signature = 'noerd:publish-home {--force : Overwrite existing file}';

    protected $description = 'Publish the noerd-home view for customization';

    public function handle(): int
    {
        $source = __DIR__ . '/../../resources/views/components/noerd-home.blade.php';
        $target = resource_path('views/components/noerd-home.blade.php');

        if (File::exists($target) && ! $this->option('force')) {
            $this->error('Home view already exists. Use --force to overwrite.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);

        $this->info('Home view published to: resources/views/components/noerd-home.blade.php');

        return self::SUCCESS;
    }
}
