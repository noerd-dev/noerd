<?php

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Noerd\Commands\Concerns\GeneratesResourceFiles;

class MakeResourceCommand extends Command
{
    use GeneratesResourceFiles;

    protected $signature = 'noerd:make-resource {model : Full model class path}';

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
        if ($result !== 0) {
            return $result;
        }

        $result = $this->selectApp();
        if ($result !== 0) {
            return $result;
        }

        $result = $this->readColumns();
        if ($result !== 0) {
            return $result;
        }

        try {
            $listBlade = $this->createListBlade();
            if ($listBlade === '') {
                return 1;
            }

            $detailBlade = $this->createDetailBlade();
            if ($detailBlade === '') {
                return 1;
            }

            $listYaml = $this->createListYaml();
            if ($listYaml === '') {
                return 1;
            }

            $detailYaml = $this->createDetailYaml();
            if ($detailYaml === '') {
                return 1;
            }

            $this->addListRoute();
            $this->addDetailRoute();
            $this->addNavigation();

            $this->line('');
            $this->info('Resource files created successfully!');

            return 0;
        } catch (Exception $e) {
            $this->error('Error creating resource: ' . $e->getMessage());

            return 1;
        }
    }
}
