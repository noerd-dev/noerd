<?php

namespace Noerd\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\SetupCollectionDefinition;
use Noerd\Support\SetupCollectionDefinitionData;
use RuntimeException;

class DatabaseSetupCollectionDefinitionRepository implements SetupCollectionDefinitionRepositoryContract
{
    /**
     * Per-request resolveFields cache keyed by "tenantId:filename".
     *
     * @var array<string, array<string, mixed>|null>
     */
    private static array $requestCache = [];

    public static function resetCache(): void
    {
        self::$requestCache = [];
    }

    public function all(?int $tenantId = null): Collection
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();

        return SetupCollectionDefinition::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('title_list')
            ->get()
            ->map(fn (SetupCollectionDefinition $m) => $this->toData($m));
    }

    public function find(string $filename, ?int $tenantId = null): ?SetupCollectionDefinitionData
    {
        $model = $this->findModel($filename, $tenantId);

        return $model ? $this->toData($model) : null;
    }

    public function findByKey(string $key, ?int $tenantId = null): ?SetupCollectionDefinitionData
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();

        $model = SetupCollectionDefinition::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('key', mb_strtoupper($key))
            ->first();

        return $model ? $this->toData($model) : null;
    }

    public function exists(string $filename, ?int $tenantId = null): bool
    {
        return $this->findModel($filename, $tenantId) !== null;
    }

    public function resolveFields(string $filename): ?array
    {
        $tenantId = TenantHelper::getSelectedTenantId();
        $cacheKey = ($tenantId ?? 'null') . ':' . $filename;

        if (array_key_exists($cacheKey, self::$requestCache)) {
            return self::$requestCache[$cacheKey];
        }

        return self::$requestCache[$cacheKey] = $this->resolveFieldsUncached($filename, $tenantId);
    }

    private function resolveFieldsUncached(string $filename, ?int $tenantId): ?array
    {
        $query = SetupCollectionDefinition::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        $model = (clone $query)->where('filename', $filename)->first();

        if (! $model) {
            // Fallback: caller may have passed the key (uppercase) by mistake.
            $model = $query->where('key', mb_strtoupper($filename))->first();
        }

        if (! $model) {
            return null;
        }

        $fields = [];
        foreach ($model->fields ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');
            $fields[] = array_merge($field, [
                'name' => 'detailData.' . ltrim(preg_replace('/^(model\.|detailData\.)/', '', $name), '.'),
                'label' => $field['label'] ?? '',
                'type' => $field['type'] ?? 'text',
                'colspan' => (int) ($field['colspan'] ?? 6),
            ]);
        }

        return [
            'title' => $model->title,
            'titleList' => $model->title_list,
            'key' => $model->key,
            'description' => $model->description ?? '',
            'fields' => $fields,
        ];
    }

    public function save(SetupCollectionDefinitionData $data, ?string $originalFilename = null, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();
        if ($tenantId === null) {
            throw new RuntimeException('Cannot save a setup collection definition without a tenant context.');
        }

        $existing = $originalFilename !== null
            ? $this->findModel($originalFilename, $tenantId)
            : null;

        $attributes = [
            'tenant_id' => $tenantId,
            'filename' => $data->filename,
            'key' => mb_strtoupper($data->key),
            'title' => $data->title,
            'title_list' => $data->titleList,
            'description' => $data->description,
            'fields' => $data->fields,
        ];

        if ($existing) {
            $existing->update($attributes);
            $model = $existing;
        } else {
            $attributes['created_by'] = Auth::id();
            $model = SetupCollectionDefinition::create($attributes);
        }

        self::resetCache();

        return $model->filename;
    }

    public function copy(string $filename, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();
        $source = $this->findModel($filename, $tenantId);
        if (! $source) {
            throw new RuntimeException("Setup collection definition '{$filename}' not found.");
        }

        $newFilename = $filename . '2';
        if ($this->findModel($newFilename, $tenantId)) {
            throw new RuntimeException("Setup collection definition '{$newFilename}' already exists.");
        }

        SetupCollectionDefinition::create([
            'tenant_id' => $tenantId,
            'filename' => $newFilename,
            'key' => $source->key . '2',
            'title' => $source->title . '2',
            'title_list' => $source->title_list . '2',
            'description' => $source->description,
            'fields' => $source->fields,
            'created_by' => Auth::id(),
        ]);

        self::resetCache();

        return $newFilename;
    }

    public function delete(string $filename, ?int $tenantId = null): void
    {
        $model = $this->findModel($filename, $tenantId);
        $model?->delete();

        self::resetCache();
    }

    public function isWritable(): bool
    {
        return true;
    }

    private function findModel(string $filename, ?int $tenantId): ?SetupCollectionDefinition
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();

        return SetupCollectionDefinition::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('filename', $filename)
            ->first();
    }

    private function toData(SetupCollectionDefinition $model): SetupCollectionDefinitionData
    {
        $fields = [];
        foreach ($model->fields ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');
            $fields[] = array_merge($field, [
                'name' => preg_replace('/^(model\.|detailData\.)/', '', $name),
                'label' => $field['label'] ?? '',
                'type' => $field['type'] ?? 'text',
                'colspan' => (int) ($field['colspan'] ?? 6),
            ]);
        }

        return new SetupCollectionDefinitionData(
            filename: $model->filename,
            key: $model->key,
            title: $model->title,
            titleList: $model->title_list,
            description: $model->description,
            fields: $fields,
            createdBy: $model->created_by,
        );
    }
}
