<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Noerd\Commands\Concerns\PublishesNoerdContent;

class NoerdUpdateCommand extends Command
{
    use PublishesNoerdContent;

    protected $signature = 'noerd:update {--force : Overwrite existing files without asking} {--build : Run npm build after update}';

    protected $description = 'Refresh the published setup app configs, config and frontend assets of an existing installation';

    public function handle(): int
    {
        $this->info('Updating noerd content...');

        try {
            if (! $this->publishNoerdContent()) {
                return self::FAILURE;
            }

            if ($this->option('build')) {
                $this->newLine();
                $this->executeNpmBuild();
            }

            $this->info('Noerd content successfully updated!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error updating noerd content: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
