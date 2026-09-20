<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Publishes a module's bundled Claude Code skills ({module}/skills/*) into the
 * host's .claude/skills. Boost skills (resources/boost/skills) are NOT handled
 * here — Boost renders those itself, see RegistersBoostPackage.
 */
trait PublishesSkills
{
    /**
     * The module root (composer.json, resources/boost, skills/).
     */
    abstract protected function getModuleRoot(): string;

    /**
     * Publish all bundled Claude Code skills (every subdir of {module}/skills/)
     * into base_path('.claude/skills'). Prefers a relative symlink so the
     * skill auto-updates with the module; falls back to a recursive copy.
     *
     * When $refreshCopies is true (update mode), stale copies are replaced.
     * Symlinks are left alone (they reference source live).
     */
    protected function publishSkills(bool $refreshCopies = false): void
    {
        $skillsRoot = $this->getModuleRoot() . '/skills';

        if (! is_dir($skillsRoot)) {
            return;
        }

        $entries = glob($skillsRoot . '/*', GLOB_ONLYDIR) ?: [];
        if (empty($entries)) {
            return;
        }

        $targetSkillsDir = base_path('.claude/skills');

        if (! is_dir($targetSkillsDir) && ! mkdir($targetSkillsDir, 0755, true) && ! is_dir($targetSkillsDir)) {
            $this->warn('Could not create .claude/skills directory; skills not published.');

            return;
        }

        foreach ($entries as $sourcePath) {
            $resolved = realpath($sourcePath);
            if ($resolved === false) {
                continue;
            }
            $this->publishSingleSkill($resolved, $targetSkillsDir, basename($sourcePath), $refreshCopies);
        }
    }

    private function publishSingleSkill(string $source, string $skillsDir, string $name, bool $refreshCopies): void
    {
        $target = $skillsDir . '/' . $name;

        if (is_link($target)) {
            $this->line("<comment>Claude skill already linked:</comment> .claude/skills/{$name}");

            return;
        }

        if (is_dir($target)) {
            if (! $refreshCopies) {
                $this->line("<comment>Claude skill already published:</comment> .claude/skills/{$name}");

                return;
            }
            $this->removeDirectoryTree($target);
            $this->line("<comment>Refreshing Claude skill:</comment> .claude/skills/{$name}");
        } elseif (file_exists($target)) {
            @unlink($target);
        }

        $relativeSource = $this->relativePath(from: $skillsDir, to: $source);

        if (@symlink($relativeSource, $target)) {
            $this->line("<info>Published Claude skill:</info> .claude/skills/{$name} → {$relativeSource}");

            return;
        }

        $this->warn("Symlink failed for skill '{$name}'; copying files instead.");
        $this->copyDirectoryTree($source, $target);
        $this->line("<info>Published Claude skill (copied):</info> .claude/skills/{$name}");
    }

    private function relativePath(string $from, string $to): string
    {
        $fromParts = explode('/', mb_rtrim($from, '/'));
        $toParts = explode('/', mb_rtrim($to, '/'));

        while ($fromParts && $toParts && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        return str_repeat('../', count($fromParts)) . implode('/', $toParts);
    }

    private function copyDirectoryTree(string $source, string $destination): void
    {
        if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function removeDirectoryTree(string $path): void
    {
        if (! is_dir($path) || is_link($path)) {
            @unlink($path);

            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            if (is_dir($full) && ! is_link($full)) {
                $this->removeDirectoryTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
