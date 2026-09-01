<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Noerd\Contracts\RunsAfterModuleUpdates;
use Noerd\Traits\RequiresNoerdInstallation;
use Throwable;

class NoerdUpdateAllCommand extends Command
{
    use RequiresNoerdInstallation;

    private const CORE = 'noerd:update';

    protected $signature = 'noerd:update-all
        {--force : Overwrite existing files without asking}
        {--build : Run npm build after the core noerd:update}
        {--except=* : Skip a command; module key or full name (--except=cms, --except=noerd:update)}';

    protected $description = 'Run noerd:update and every installed module\'s noerd:update-{module} command';

    /**
     * Only the modules whose service provider is loaded register an update command,
     * so the queue is discovered instead of hardcoded — exactly the set of modules
     * this installation actually has.
     */
    public function handle(): int
    {
        if (!$this->ensureNoerdInstalled()) {
            return self::FAILURE;
        }

        if ($this->getApplication() === null) {
            $this->error('noerd:update-all must be run through Artisan.');

            return self::FAILURE;
        }

        [$queue, $skipped] = $this->partition($this->discover());

        if ($queue === []) {
            $this->warn('No noerd update commands found.');

            return self::SUCCESS;
        }

        $this->info('The following update commands will run:');
        foreach ($queue as $name) {
            $this->line('  - ' . $name);
        }
        foreach ($skipped as $name) {
            $this->line("  <comment>- {$name} (skipped)</comment>");
        }

        if (!$this->option('force') && !$this->confirmWithoutForce(count($queue))) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $results = [];
        foreach ($queue as $name) {
            $this->newLine();
            $this->line('<fg=cyan>── ' . $name . ' ' . str_repeat('─', max(0, 60 - mb_strlen($name))) . '</>');
            $results[$name] = $this->runUpdate($name);
        }

        return $this->summarize($results, $skipped);
    }

    /**
     * The core update plus every noerd:update-{module}, in a deterministic order.
     *
     * @return array<int, string>
     */
    private function discover(): array
    {
        $names = [];
        $last = [];

        foreach ($this->getApplication()->all() as $name => $command) {
            // all() is keyed by name AND by every alias, both pointing at the same instance.
            if ($name !== $command->getName()) {
                continue;
            }

            // Never recurse into itself — resolved from the name so a rename cannot break it.
            if ($name === $this->getName()) {
                continue;
            }

            if ($name !== self::CORE && !str_starts_with($name, 'noerd:update-')) {
                continue;
            }

            $names[] = $name;

            // A command that touches what other updates publish declares it.
            if ($command instanceof RunsAfterModuleUpdates) {
                $last[] = $name;
            }
        }

        $core = in_array(self::CORE, $names, true) ? [self::CORE] : [];
        sort($last);
        // Module updates only write into their own app-configs/{key}/, so they are
        // order-independent — sorted purely for a reproducible run.
        $middle = array_values(array_diff($names, $core, $last));
        sort($middle);

        return array_merge($core, $middle, $last);
    }

    /**
     * @param  array<int, string>  $names
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function partition(array $names): array
    {
        $except = array_map(
            static fn(string $value): string => str_contains($value, ':') ? $value : 'noerd:update-' . $value,
            (array) $this->option('except'),
        );

        return [
            array_values(array_diff($names, $except)),
            array_values(array_intersect($names, $except)),
        ];
    }

    /**
     * Without --force every sub-command asks per existing file, so the run is gated
     * once up front instead of hiding that decision fifteen commands deep.
     */
    private function confirmWithoutForce(int $queueSize): bool
    {
        $this->warn('Without --force every command asks per existing file (default: skip),');
        $this->warn('and under --no-interaction all existing files are skipped — only missing files are copied.');

        if (!$this->input->isInteractive()) {
            return true;
        }

        return $this->confirm("Run these {$queueSize} update commands now?", true);
    }

    /**
     * @return array{status: string, message: string}
     */
    private function runUpdate(string $name): array
    {
        $parameters = [];

        if ($this->option('force')) {
            $parameters['--force'] = true;
        }

        // Only noerd:update defines --build; passing it to a module command throws.
        if ($name === self::CORE && $this->option('build')) {
            $parameters['--build'] = true;
        }

        try {
            // call() (not Artisan::call) forwards --no-interaction/--quiet/--verbose to
            // the child and shares this command's output, so sub-command output streams.
            $exitCode = $this->call($name, $parameters);
        } catch (Throwable $e) {
            $this->error("{$name} failed: " . $e->getMessage());

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }

        return $exitCode === self::SUCCESS
            ? ['status' => 'updated', 'message' => '']
            : ['status' => 'failed', 'message' => 'exit code ' . $exitCode];
    }

    /**
     * @param  array<string, array{status: string, message: string}>  $results
     * @param  array<int, string>  $skipped
     */
    private function summarize(array $results, array $skipped): int
    {
        $rows = [];
        foreach ($results as $name => $result) {
            $rows[] = [
                $name,
                $result['status'] === 'updated' ? '<info>updated</info>' : '<error>failed</error>',
                $result['message'],
            ];
        }
        foreach ($skipped as $name) {
            $rows[] = [$name, '<comment>skipped</comment>', '--except'];
        }

        $failed = array_filter($results, static fn(array $result): bool => $result['status'] === 'failed');

        $this->newLine();
        $this->info('Update summary:');
        $this->table(['Command', 'Result', 'Details'], $rows);
        $this->line(sprintf(
            '%d updated, %d failed, %d skipped.',
            count($results) - count($failed),
            count($failed),
            count($skipped),
        ));

        if ($failed !== []) {
            $this->error('Some update commands failed: ' . implode(', ', array_keys($failed)));

            return self::FAILURE;
        }

        $this->info('All noerd modules updated.');

        return self::SUCCESS;
    }
}
