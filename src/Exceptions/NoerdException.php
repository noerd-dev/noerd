<?php

declare(strict_types=1);

namespace Noerd\Exceptions;

use Exception;
use Illuminate\Http\Response;
use Noerd\Enums\NoerdExceptionType;

/**
 * An app the current user cannot work in: not assigned to the tenant, or denied
 * to the user. Both are access problems from the user's point of view, so the
 * exception renders the friendly error page with a 403.
 */
class NoerdException extends Exception
{
    public function __construct(
        public NoerdExceptionType $type,
        public ?string $appName = null,
    ) {
        parent::__construct(match ($type) {
            NoerdExceptionType::AppNotAssigned => "App '{$appName}' is not assigned to this tenant",
            NoerdExceptionType::AppAccessDenied => "App '{$appName}' is not accessible for this user",
        });
    }

    public function render(): Response
    {
        return response()->view('noerd::errors.noerd-error', [
            // The view branches on the raw key, not the enum case.
            'type' => $this->type->value,
            'appName' => $this->appName,
        ], 403);
    }
}
