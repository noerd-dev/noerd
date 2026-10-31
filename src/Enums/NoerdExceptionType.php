<?php

declare(strict_types=1);

namespace Noerd\Enums;

/**
 * The failure kinds NoerdException reports. The backing values are the keys the
 * error view (`noerd::errors.noerd-error`) branches on.
 */
enum NoerdExceptionType: string
{
    case AppNotAssigned = 'app_not_assigned';

    case AppAccessDenied = 'app_access_denied';
}
