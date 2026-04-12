<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class NoerdInfoCommand extends Command
{
    protected $signature = 'noerd:info';

    protected $description = 'Display the current Noerd version';

    public function handle(): int
    {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';

        $version = 'unknown';

        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);
            $version = $composer['version'] ?? 'unknown';
        }

        $this->info("Noerd {$version}");

        return self::SUCCESS;
    }
}
