<?php

declare(strict_types=1);

namespace Noerd\Exceptions;

use RuntimeException;

/**
 * A tenant was to be deleted although a module still holds a reason against
 * it (see TenantDeletionService::registerBlocker()).
 */
final class TenantDeletionBlockedException extends RuntimeException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(implode(' ', $reasons));
    }
}
