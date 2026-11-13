<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\File;

/**
 * Rewrites app-config YAML written in an earlier vocabulary to the current
 * one. It works line by line on the source text — comments, order and
 * formatting of a file stay as they are — and is idempotent: a file in the
 * current vocabulary is not touched.
 *
 * `noerd:update` and every `noerd:update-{module}` run it over the project's
 * app-configs (and the configs of its local modules), `noerd:lint` reports a
 * file that still carries an old name.
 *
 * @internal
 */
final class YamlVocabularyMigrator
{
    /**
     * Column types that were renamed; the value of a `type:` key.
     *
     * @var array<string, string>
     */
    public const COLUMN_TYPES = [
        'badge_with_text' => 'badgeWithText',
        'colored_text' => 'coloredText',
        'relation_link' => 'relationLink',
        'inversebool' => 'inverseBool',
    ];

    /**
     * Column types that were renamed in list configs only — the name means
     * something else in other files (a validation rule, a form field).
     *
     * @var array<string, string>
     */
    public const LIST_COLUMN_TYPES = [
        'boolean' => 'bool',
    ];

    /**
     * Keys that were renamed.
     *
     * @var array<string, string>
     */
    public const KEYS = [
        'block_menus' => 'blockMenus',
    ];

    /**
     * Whether a path (relative to an app-configs directory) is a list config:
     * `{app}/lists/…`.
     */
    public static function isListConfig(string $relativePath): bool
    {
        $segments = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));

        return in_array('lists', array_slice($segments, 0, -1), true);
    }

    /**
     * Migrate every YAML file below the given directories.
     *
     * @param  list<string>  $directories
     * @return list<string> the files that were (or, in a dry run, would be) rewritten
     */
    public function migrate(array $directories, bool $dryRun = false): array
    {
        $changed = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if (! in_array($file->getExtension(), ['yml', 'yaml'], true)) {
                    continue;
                }

                $path = $file->getPathname();
                $source = (string) file_get_contents($path);
                $migrated = $this->migrateSource($source, self::isListConfig($file->getRelativePathname()));

                if ($migrated === $source) {
                    continue;
                }

                if (! $dryRun) {
                    file_put_contents($path, $migrated);
                }

                $changed[] = $path;
            }
        }

        return $changed;
    }

    /**
     * The source of one YAML file in the current vocabulary.
     */
    public function migrateSource(string $source, bool $listConfig = false): string
    {
        $types = $listConfig ? [...self::COLUMN_TYPES, ...self::LIST_COLUMN_TYPES] : self::COLUMN_TYPES;

        $lines = preg_split('/(?<=\n)/', $source) ?: [];

        foreach ($lines as $index => $line) {
            $lines[$index] = $this->migrateLine($line, $types);
        }

        return implode('', $lines);
    }

    /**
     * The old names a source still carries (for the linter).
     *
     * @return list<string>
     */
    public function outdatedNames(string $source, bool $listConfig = false): array
    {
        $types = $listConfig ? [...self::COLUMN_TYPES, ...self::LIST_COLUMN_TYPES] : self::COLUMN_TYPES;
        $found = [];

        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            if (preg_match(self::typePattern($types), $line, $match)) {
                $found[] = 'type: ' . $match['type'];
            }

            if (preg_match(self::keyPattern(), $line, $match)) {
                $found[] = $match['key'] . ':';
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * `type: colored_text`, optionally quoted, as a mapping entry or the first
     * key of a list item, with an optional trailing comment.
     *
     * @param  array<string, string>  $types
     */
    private static function typePattern(array $types): string
    {
        $names = implode('|', array_map(static fn(string $name): string => preg_quote($name, '/'), array_keys($types)));

        return '/^(?<head>\s*(?:-\s+)?type:\s*)(?<quote>[\'"]?)(?<type>' . $names . ')\k<quote>(?<tail>\s*(?:#.*)?\R?)$/';
    }

    private static function keyPattern(): string
    {
        $names = implode('|', array_map(static fn(string $name): string => preg_quote($name, '/'), array_keys(self::KEYS)));

        return '/^(?<head>\s*(?:-\s+)?)(?<key>' . $names . '):(?<tail>.*\R?)$/s';
    }

    /**
     * @param  array<string, string>  $types
     */
    private function migrateLine(string $line, array $types): string
    {
        $line = (string) preg_replace_callback(
            self::typePattern($types),
            static fn(array $match): string => $match['head'] . $match['quote'] . $types[$match['type']] . $match['quote'] . $match['tail'],
            $line,
        );

        return (string) preg_replace_callback(
            self::keyPattern(),
            static fn(array $match): string => $match['head'] . self::KEYS[$match['key']] . ':' . $match['tail'],
            $line,
        );
    }
}
