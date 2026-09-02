<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Noerd\Commands\Concerns\GeneratesResourceFiles;

class MakeListCommand extends Command
{
    use GeneratesResourceFiles;

    protected $signature = 'noerd:make-list {model : Full model class path} {--app= : App name (e.g. crm)}';

    protected $description = 'Generate a list Blade file from an existing Eloquent model';

    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    public function handle(): int
    {
        $result = $this->initializeFromModel($this->argument('model'));
        if ($result !== self::SUCCESS) {
            return $result;
        }

        $result = $this->selectApp($this->option('app'));
        if ($result !== self::SUCCESS) {
            return $result;
        }

        $result = $this->readColumns();
        if ($result !== self::SUCCESS) {
            return $result;
        }

        try {
            $this->createListBlade();

            $this->createListYaml();

            $this->addListRoute();

            $this->addNavigation();

            $this->line('');
            $this->info('List files created successfully!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error creating list: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
