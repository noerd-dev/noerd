<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Closure;
use Illuminate\Support\Arr;

use function Livewire\before;

use Livewire\ComponentHookRegistry;
use Noerd\Support\ClientWritablePaths;
use Noerd\Traits\NoerdPage;

/**
 * Keeps client updates away from the paths a noerd form does not let the user
 * edit (see ClientWritablePaths): `readonly: true` fields, the unbound keys
 * below a nested root such as `custom_attributes`, and every top-level key of
 * a bound property (`detailData.tenant_id`, `detailData.id`) that no field and
 * no additionalDetailFields() entry declares.
 *
 * The values are captured right after hydration — from the checksummed
 * snapshot, i.e. what the server rendered — and every protected path a client
 * update touched is put back. Reverting instead of rejecting is deliberate: a
 * read-only currency input still sends its parsed value on blur, and that
 * must not break the page. The revert is registered as a `before` listener of
 * Livewire's update event, so it runs ahead of the component's `updated*`
 * hooks — they never see the discarded value. Server-side writes (mount,
 * actions, select handlers) are untouched: they never pass the update channel.
 *
 * @internal
 */
final class ClientWriteGuardHook extends NoerdComponentHook
{
    protected const TRAITS = [NoerdPage::class];

    private ?ClientWritablePaths $paths = null;

    /** @var array<string, array<mixed>> */
    private array $authentic = [];

    private bool $guardsModelId = false;

    private mixed $authenticModelId = null;

    public static function provide(): void
    {
        before('update', function (object $component, string $fullPath, mixed ...$rest): ?Closure {
            // A component this hook skipped has no instance here — nothing to guard.
            $hook = ComponentHookRegistry::getHook($component, self::class);

            return $hook instanceof self ? $hook->guard($fullPath) : null;
        });
    }

    public function hydrate(mixed $memo = null): void
    {
        if (property_exists($this->component, 'modelId')) {
            $this->guardsModelId = true;
            $this->authenticModelId = $this->component->modelId;
        }

        $paths = ClientWritablePaths::forComponent($this->component);

        foreach ($paths->properties() as $property) {
            $value = property_exists($this->component, $property) ? ($this->component->{$property} ?? null) : null;

            if (is_array($value)) {
                $this->authentic[$property] = $value;
            }
        }

        $this->paths = $this->authentic === [] ? null : $paths;
    }

    /**
     * The server's value of the root with only its bound, editable paths
     * taken from the client.
     *
     * @param  array<mixed>  $current
     * @param  array<mixed>  $authentic
     * @param  list<string>  $allowed
     * @return array<mixed>
     */
    private static function rebuildNestedRoot(array $current, array $authentic, string $root, array $allowed): array
    {
        $original = Arr::get($authentic, $root);
        $rebuilt = is_array($original) ? $original : [];
        $tookClientValue = false;

        foreach ($allowed as $path) {
            if (Arr::has($current, $path)) {
                Arr::set($rebuilt, mb_substr($path, mb_strlen($root) + 1), Arr::get($current, $path));
                $tookClientValue = true;
            }
        }

        if ($tookClientValue) {
            Arr::set($current, $root, $rebuilt);

            return $current;
        }

        return self::restorePath($current, $authentic, $root);
    }

    /**
     * @param  array<mixed>  $current
     * @param  array<mixed>  $authentic
     * @return array<mixed>
     */
    private static function restorePath(array $current, array $authentic, string $path): array
    {
        if (Arr::has($authentic, $path)) {
            Arr::set($current, $path, Arr::get($authentic, $path));
        } else {
            Arr::forget($current, $path);
        }

        return $current;
    }

    /**
     * Restores one top-level key literally — a key may contain a dot, which
     * Arr::set() would read as a path.
     *
     * @param  array<mixed>  $current
     * @param  array<mixed>  $authentic
     * @return array<mixed>
     */
    private static function restoreKey(array $current, array $authentic, string $key): array
    {
        if (array_key_exists($key, $authentic)) {
            $current[$key] = $authentic[$key];
        } else {
            unset($current[$key]);
        }

        return $current;
    }

    private function guard(string $fullPath): ?Closure
    {
        if ($fullPath === 'modelId' && $this->guardsModelId) {
            return function (): void {
                if ((string) $this->component->modelId !== (string) $this->authenticModelId) {
                    $this->component->modelId = $this->authenticModelId;
                }
            };
        }

        if ($this->paths === null || $this->paths->allows($fullPath)) {
            return null;
        }

        return function () use ($fullPath): void {
            $this->restore($fullPath);
        };
    }

    private function restore(string $fullPath): void
    {
        foreach ($this->authentic as $property => $authentic) {
            if (! ClientWritablePaths::touches($fullPath, $property)) {
                continue;
            }

            $current = $this->component->{$property};
            $current = is_array($current) ? $current : [];

            foreach ($this->paths->nestedRootsOf($property) as $root => $allowed) {
                if (ClientWritablePaths::touches($fullPath, $property . '.' . $root)) {
                    $current = self::rebuildNestedRoot($current, $authentic, $root, $allowed);
                }
            }

            foreach ($this->paths->readonlyPaths($property) as $path) {
                if (ClientWritablePaths::touches($fullPath, $property . '.' . $path)) {
                    $current = self::restorePath($current, $authentic, $path);
                }
            }

            $declared = $this->paths->declaredKeysOf($property);
            foreach (array_unique([...array_keys($current), ...array_keys($authentic)]) as $key) {
                $key = (string) $key;
                if (! in_array($key, $declared, true) && ClientWritablePaths::touches($fullPath, $property . '.' . $key)) {
                    $current = self::restoreKey($current, $authentic, $key);
                }
            }

            $this->component->{$property} = $current;
        }
    }
}
