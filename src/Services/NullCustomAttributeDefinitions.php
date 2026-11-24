<?php

declare(strict_types=1);

namespace Noerd\Services;

use Noerd\Contracts\CustomAttributeDefinitionsContract;

/**
 * The default without a module managing custom attribute definitions: no model has any.
 */
final class NullCustomAttributeDefinitions implements CustomAttributeDefinitionsContract
{
    public function fieldsFor(string $modelClass, int $tenantId): array
    {
        return [];
    }
}
