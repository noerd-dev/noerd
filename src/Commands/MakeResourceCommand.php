<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Noerd\Commands\Concerns\GeneratesResourceFiles;

class MakeResourceCommand extends Command
{
    use GeneratesResourceFiles;

    protected $signature = 'noerd:make-resource {model : The model class name (e.g. Customer or App\\Models\\Customer)} {--app= : App name (e.g. crm)}';

    protected $description = 'Generate list/detail Blade and YAML files from an existing Eloquent model';

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
            $listBlade = $this->createListBlade();
            if ($listBlade === '') {
                return self::FAILURE;
            }

            $detailBlade = $this->createDetailBlade();
            if ($detailBlade === '') {
                return self::FAILURE;
            }

            $this->addListRoute();

            // The detail route must be known before the list YAML (its "New"
            // action) and the list component are annotated with it.
            if ($this->addDetailRoute()) {
                $this->annotateListDetailRoute($listBlade);
            }

            $listYaml = $this->createListYaml();
            if ($listYaml === '') {
                return self::FAILURE;
            }

            $detailYaml = $this->createDetailYaml();
            if ($detailYaml === '') {
                return self::FAILURE;
            }

            $this->addNavigation();

            $this->line('');
            $this->info('Resource files created successfully!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error creating resource: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
