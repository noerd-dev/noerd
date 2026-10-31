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

    protected $signature = 'noerd:make-resource
                            {model : The model class name (e.g. Customer or App\\Models\\Customer)}
                            {--app= : App name (e.g. crm)}
                            {--only= : Generate one half of the resource only: list or detail}';

    protected $description = 'Generate list/detail Blade and YAML files from an existing Eloquent model';

    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    public function handle(): int
    {
        $only = $this->option('only');

        if ($only !== null && ! in_array($only, ['list', 'detail'], true)) {
            $this->error("The --only option accepts 'list' or 'detail', got '{$only}'.");

            return self::FAILURE;
        }

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
            $created = match ($only) {
                'list' => $this->generateList(),
                'detail' => $this->generateDetail(),
                default => $this->generateResource(),
            };

            if (! $created) {
                return self::FAILURE;
            }

            $this->line('');
            $this->info(match ($only) {
                'list' => 'List files created successfully!',
                'detail' => 'Detail files created successfully!',
                default => 'Resource files created successfully!',
            });

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error creating resource: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * List and detail together. The detail route must be known before the list
     * YAML (its "New" action) and the list component are annotated with it.
     */
    private function generateResource(): bool
    {
        $listBlade = $this->createListBlade();
        if ($listBlade === '' || $this->createDetailBlade() === '') {
            return false;
        }

        $this->addListRoute();

        if ($this->addDetailRoute()) {
            $this->annotateListDetailRoute($listBlade);
        }

        if ($this->createListYaml() === '' || $this->createDetailYaml() === '') {
            return false;
        }

        $this->addNavigation();

        return true;
    }

    /**
     * The list alone — for a record type whose detail exists already or is
     * hand-written. A detail route that is declared already is wired up, a
     * missing one is never asked for.
     */
    private function generateList(): bool
    {
        $listBlade = $this->createListBlade();
        if ($listBlade === '') {
            return false;
        }

        $this->addListRoute();

        if ($this->detectDetailRoute()) {
            $this->annotateListDetailRoute($listBlade);
        }

        if ($this->createListYaml() === '') {
            return false;
        }

        $this->addNavigation();

        return true;
    }

    /**
     * The detail alone, with its route; the list is left as it is.
     */
    private function generateDetail(): bool
    {
        if ($this->createDetailBlade() === '') {
            return false;
        }

        $this->addDetailRoute();

        return $this->createDetailYaml() !== '';
    }
}
