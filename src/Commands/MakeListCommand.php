<?php

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
        if ($result !== 0) {
            return $result;
        }

        $result = $this->selectApp($this->option('app'));
        if ($result !== 0) {
            return $result;
        }

        $result = $this->readColumns();
        if ($result !== 0) {
            return $result;
        }

        try {
            $this->createListBlade();

            $this->createListYaml();

            $this->addListRoute();

            $this->addNavigation();

            $this->line('');
            $this->info('List files created successfully!');

            return 0;
        } catch (Exception $e) {
            $this->error('Error creating list: ' . $e->getMessage());

            return 1;
        }
    }
}
