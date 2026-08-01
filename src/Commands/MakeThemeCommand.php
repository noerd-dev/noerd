<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeThemeCommand extends Command
{
    protected $signature = 'noerd:theme {name : The theme name (folder name, kebab-case)}
        {--module= : Create the theme inside app-modules/{module} instead of the project}';

    protected $description = 'Scaffold a new form theme by copying the default theme folder';

    public function handle(): int
    {
        $name = Str::kebab((string) $this->argument('name'));

        if ($name === '' || ! preg_match('/^[a-z0-9][a-z0-9-]*$/', $name)) {
            $this->error('The theme name must be kebab-case (a-z, 0-9, dashes).');

            return self::FAILURE;
        }

        $source = __DIR__ . '/../../resources/views/themes/default';

        $module = $this->option('module');
        $target = $module
            ? base_path("app-modules/{$module}/resources/views/themes/{$name}")
            : resource_path("views/themes/{$name}");

        if ($module && ! File::isDirectory(base_path("app-modules/{$module}"))) {
            $this->error("Module [{$module}] does not exist.");

            return self::FAILURE;
        }

        if (File::isDirectory($target)) {
            $this->error("Theme folder already exists: {$target}");

            return self::FAILURE;
        }

        File::copyDirectory($source, $target);

        $themeYaml = $target . '/theme.yml';
        $yaml = File::get($themeYaml);
        $yaml = preg_replace('/^label:.*$/m', 'label: ' . Str::headline($name), $yaml, 1);
        File::put($themeYaml, $yaml);

        $this->info("Theme [{$name}] created at: {$target}");
        $this->line('Edit theme.yml (label, grid/control classes) and adapt the element templates.');
        $this->line('Elements you delete fall back to the default theme.');

        if ($module) {
            $this->newLine();
            $this->line('Register the theme root in the module service provider boot():');
            $this->line("  app(\\Noerd\\Services\\ThemeRegistry::class)->registerPath(__DIR__ . '/../../resources/views/themes');");
        } else {
            $this->line('Project themes are discovered automatically — pick the theme in Setup -> System Settings');
            $this->line("or set `theme: {$name}` in a detail YAML.");
        }

        return self::SUCCESS;
    }
}
