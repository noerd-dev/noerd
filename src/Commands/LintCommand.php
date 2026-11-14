<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Noerd\Support\ConfigLinter;
use Noerd\Support\ModulesPath;

class LintCommand extends Command
{
    protected $signature = 'noerd:lint
        {--path=* : Further app-configs directories to check (e.g. a vendor module\'s app-configs)}
        {--project-only : Check the project\'s app-configs only, not the app-configs of the local modules}
        {--strict : Fail on warnings as well}';

    protected $description = 'Check the app-configs YAML for broken field types, routes, picklist providers and columns';

    public function handle(ConfigLinter $linter): int
    {
        // The project copy AND the module copies (the templates every install
        // publishes) — a module's outdated name would otherwise come back with
        // the next noerd:update-{module}.
        $directories = [
            base_path('app-configs'),
            ...($this->option('project-only') ? [] : (glob(ModulesPath::absolute('*/app-configs'), GLOB_ONLYDIR) ?: [])),
            ...array_map(
                fn(string $path): string => str_starts_with($path, '/') ? $path : base_path($path),
                (array) $this->option('path'),
            ),
        ];

        $issues = $linter->lint($directories);

        if ($issues === []) {
            $this->info('No issues found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Severity', 'File', 'Location', 'Message'],
            array_map(fn(array $issue): array => [
                $issue['severity'],
                str_replace(base_path() . '/', '', $issue['file']),
                $issue['location'],
                $issue['message'],
            ], $issues),
        );

        $errors = count(array_filter($issues, fn(array $issue): bool => $issue['severity'] === ConfigLinter::ERROR));
        $warnings = count($issues) - $errors;

        $this->line("{$errors} error(s), {$warnings} warning(s).");

        return $errors > 0 || ($this->option('strict') && $warnings > 0) ? self::FAILURE : self::SUCCESS;
    }
}
