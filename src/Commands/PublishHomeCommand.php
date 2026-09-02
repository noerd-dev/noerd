<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishHomeCommand extends Command
{
    protected $signature = 'noerd:publish-home {--force : Overwrite existing file}';

    protected $description = 'Publish the noerd-apps view for customization';

    public function handle(): int
    {
        $source = __DIR__ . '/../../resources/views/components/noerd-apps.blade.php';
        $target = resource_path('views/components/noerd-apps.blade.php');

        if (File::exists($target) && ! $this->option('force')) {
            $this->error('Home view already exists. Use --force to overwrite.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);

        $this->info('Home view published to: resources/views/components/noerd-apps.blade.php');

        return self::SUCCESS;
    }
}
