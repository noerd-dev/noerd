<?php

declare(strict_types=1);

namespace Noerd\Attributes;

use Attribute;

/**
 * Marks a model method a list column or detail field may call through
 * `method:` in its YAML. Only marked methods are ever called — a YAML entry
 * can never reach `delete()`, `save()` or any other method.
 *
 *     #[ComputedValue(with: ['orders'])]
 *     public function lastOrder(): ?CarbonInterface
 *
 * `with` names the relations the method reads; lists eager-load them once per
 * page instead of once per row.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ComputedValue
{
    /**
     * @param  array<int, string>  $with
     */
    public function __construct(public array $with = []) {}
}
