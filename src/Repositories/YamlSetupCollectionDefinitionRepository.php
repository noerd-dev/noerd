<?php

namespace Noerd\Repositories;

use Illuminate\Support\Collection;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Support\SetupCollectionDefinitionData;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
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

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        try {
            $fields = Yaml::parse($content) ?: [];
        } catch (Throwable) {
            return null;
        }

        if (! is_array($fields)) {
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
        return $this->basePath . '/' . $filename . '.yml';
    }

    private function loadFile(string $path): ?SetupCollectionDefinitionData
    {
        try {
            $content = Yaml::parseFile($path);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($content)) {
            return null;
        }

        return SetupCollectionDefinitionData::fromArray($content, pathinfo($path, PATHINFO_FILENAME));
    }
}
