<?php

declare(strict_types=1);

namespace Noerd\Repositories;

use Illuminate\Support\Collection;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Support\SetupCollectionDefinitionData;
use RuntimeException;
use Throwable;

class YamlSetupCollectionDefinitionRepository implements SetupCollectionDefinitionRepositoryContract
{
    public function __construct(private readonly string $basePath) {}

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function all(?int $tenantId = null): Collection
    {
        if (! is_dir($this->basePath)) {
            return collect();
        }

        $files = glob($this->basePath . '/*.yml') ?: [];

        return collect($files)
            ->map(fn(string $path) => $this->loadFile($path))
            ->filter()
            ->sortBy(fn(SetupCollectionDefinitionData $d) => mb_strtolower($d->titleList))
            ->values();
    }

    public function find(string $filename, ?int $tenantId = null): ?SetupCollectionDefinitionData
    {
        $path = $this->pathFor($filename);

        return file_exists($path) ? $this->loadFile($path) : null;
    }

    public function findByKey(string $key, ?int $tenantId = null): ?SetupCollectionDefinitionData
    {
        $key = mb_strtoupper($key);

        return $this->all()->first(fn(SetupCollectionDefinitionData $d) => $d->key === $key);
    }

    public function exists(string $filename, ?int $tenantId = null): bool
    {
        return file_exists($this->pathFor($filename));
    }

    public function resolveFields(string $filename): ?array
    {
        $path = $this->pathFor($filename);
        if (! file_exists($path)) {
            return null;
        }

        try {
            $fields = StaticConfigHelper::parseYamlFile($path);
        } catch (Throwable) {
            return null;
        }

        $fields['fields'] = array_values($fields['fields'] ?? []);

        return $fields;
    }

    public function save(SetupCollectionDefinitionData $data, ?string $originalFilename = null, ?int $tenantId = null): string
    {
        throw new RuntimeException('Setup collection definitions are read-only in YAML mode. Deploy changes via YAML files.');
    }

    public function copy(string $filename, ?int $tenantId = null): string
    {
        throw new RuntimeException('Setup collection definitions are read-only in YAML mode. Deploy changes via YAML files.');
    }

    public function delete(string $filename, ?int $tenantId = null): void
    {
        throw new RuntimeException('Setup collection definitions are read-only in YAML mode. Deploy changes via YAML files.');
    }

    public function isWritable(): bool
    {
        return false;
    }

    private function pathFor(string $filename): string
    {
        // The filename comes from a client-supplied collection key (?key=…,
        // mount arguments), so it must be a bare identifier — otherwise it
        // reads any .yml on the filesystem. Mirrors the guard in
        // StaticConfigHelper::resolveConfigPath().
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $filename)) {
            return $this->basePath . '/__invalid__.yml';
        }

        return $this->basePath . '/' . $filename . '.yml';
    }

    private function loadFile(string $path): ?SetupCollectionDefinitionData
    {
        try {
            // Shared mtime-guarded cache — the navigation build reads every
            // collection YAML too, so each file parses once per process.
            $content = StaticConfigHelper::parseYamlFile($path);
        } catch (Throwable) {
            return null;
        }

        if ($content === []) {
            return null;
        }

        return SetupCollectionDefinitionData::fromArray($content, pathinfo($path, PATHINFO_FILENAME));
    }
}
