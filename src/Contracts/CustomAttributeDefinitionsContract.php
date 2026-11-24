<?php

declare(strict_types=1);

namespace Noerd\Contracts;

use Noerd\Support\CustomAttributeField;

/**
 * The custom attributes a tenant defined for a model — the keys of its
 * `custom_attributes` JSON column. Code that writes custom attributes outside
 * a detail form (an MCP tool, an import) reads the definitions only through
 * this contract, never from the module that stores them. The core binds an
 * empty default; a module that manages definitions rebinds it.
 */
interface CustomAttributeDefinitionsContract
{
    /**
     * The ACTIVE definitions of the model's table for the tenant, in display order.
     *
     * @param  class-string  $modelClass
     * @return list<CustomAttributeField>
     */
    public function fieldsFor(string $modelClass, int $tenantId): array;
}
