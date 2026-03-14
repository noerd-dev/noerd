<?php

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Noerd\Commands\Concerns\GeneratesResourceFiles;

class MakePageCommand extends Command
{
    use GeneratesResourceFiles;

    protected $signature = 'noerd:make-page {name : Page name in kebab-case (e.g. sent-mails)} {--app= : App name (e.g. liefertool)}';

    protected $description = 'Generate a standalone page Blade file (no model required)';

    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    public function handle(): int
    {
        $this->initializeFromEntity($this->argument('name'));

        $result = $this->selectApp($this->option('app'));
        if ($result !== 0) {
            return $result;
        }

        try {
            $this->createPageBlade();

            $this->addPageRoute();

            $this->addNavigation(useSingularRoute: true);

            $this->line('');
            $this->info('Page files created successfully!');

            return 0;
        } catch (Exception $e) {
            $this->error('Error creating page: ' . $e->getMessage());

            return 1;
        }
    }
}
