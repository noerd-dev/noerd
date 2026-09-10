<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * The host project's `boost.json` — the file Laravel Boost reads to decide
 * WHICH third-party packages get their guidelines and skills rendered into the
 * agent files (`packages`) and which skills it currently tracks (`skills`).
 *
 * This class only ever ADDS entries. It never removes anything, never creates
 * the file (a missing boost.json means Boost is not set up — that is the
 * developer's call) and writes in Boost's own format (4-space pretty print,
 * unescaped slashes, trailing newline) while keeping the existing key order.
 * Boost itself is not a dependency of noerd, so nothing here references it.
 */
final class BoostConfig
{
    private static bool $refreshedInProcess = false;

    public function __construct(private readonly string $path) {}

    public static function forProject(): self
    {
        return new self(base_path('boost.json'));
    }

    /**
     * Whether `boost:update` already ran in this PHP process — `noerd:update-all`
     * chains a dozen commands that would otherwise each re-render the agent
     * files; a rerun is only needed when a command added a new entry.
     */
    public static function wasRefreshedInProcess(): bool
    {
        return self::$refreshedInProcess;
    }

    public static function markRefreshedInProcess(bool $refreshed = true): void
    {
        self::$refreshedInProcess = $refreshed;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Whether the file decodes to a JSON object (Boost refuses anything else).
     */
    public function isValid(): bool
    {
        return $this->exists() && $this->read() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->read() ?? [];
    }

    /**
     * @return string[]
     */
    public function packages(): array
    {
        return $this->stringList('packages');
    }

    /**
     * @return string[]
     */
    public function skills(): array
    {
        return $this->stringList('skills');
    }

    /**
     * Add the given packages and skill names when they are missing and return
     * what was actually added. Nothing is written when nothing changed, when the
     * file is missing, or when it does not hold a JSON object.
     *
     * @param  string[]  $packages
     * @param  string[]  $skills
     * @return array{packages: string[], skills: string[]}
     */
    public function ensure(array $packages, array $skills): array
    {
        $added = ['packages' => [], 'skills' => []];

        $config = $this->read();
        if ($config === null) {
            return $added;
        }

        foreach (['packages' => $packages, 'skills' => $skills] as $key => $wanted) {
            $current = $this->stringList($key, $config);

            foreach ($wanted as $name) {
                if (! in_array($name, $current, true)) {
                    $current[] = $name;
                    $added[$key][] = $name;
                }
            }

            if ($added[$key] !== []) {
                $config[$key] = array_values($current);
            }
        }

        if ($added['packages'] !== [] || $added['skills'] !== []) {
            $this->write($config);
        }

        return $added;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(): ?array
    {
        if (! $this->exists()) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return string[]
     */
    private function stringList(string $key, ?array $config = null): array
    {
        $value = ($config ?? $this->all())[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function write(array $config): void
    {
        file_put_contents(
            $this->path,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
        );
    }
}
