<?php

namespace Noerd\Support;

class FieldTypeDefinition
{
    /**
     * @param  array<string, mixed>  $props
     * @param  callable|null  $resolver
     * @param  callable|null  $keyResolver
     */
    public function __construct(
        public string $kind,
        public string $target,
        public array $props = [],
        public $resolver = null,
        public $keyResolver = null,
    ) {}

    /**
     * @param  array<string, mixed>  $props
     */
    public static function include(string $target, array $props = [], ?callable $resolver = null): self
    {
        return new self('include', $target, $props, $resolver);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    public static function livewire(string $target, array $props = [], ?callable $resolver = null, ?callable $keyResolver = null): self
    {
        return new self('livewire', $target, $props, $resolver, $keyResolver);
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    public function resolveProps(array $field, mixed $component = null, mixed $detailData = null, mixed $modelId = null): array
    {
        $resolvedProps = $this->props;

        if ($this->resolver) {
            $resolvedProps = array_merge(
                $resolvedProps,
                ($this->resolver)($field, $component, $detailData, $modelId),
            );
        }

        return $resolvedProps;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function resolveKey(array $field, mixed $component = null, mixed $detailData = null, mixed $modelId = null): ?string
    {
        if (! $this->keyResolver) {
            return null;
        }

        return ($this->keyResolver)($field, $component, $detailData, $modelId);
    }
}
