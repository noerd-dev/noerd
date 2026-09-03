<?php

declare(strict_types=1);

namespace Noerd\Exceptions;

use Exception;
use Illuminate\Http\Response;
use Noerd\Enums\NoerdExceptionType;

class NoerdException extends Exception
{
    public function __construct(
        public NoerdExceptionType $type,
        public ?string $appName = null,
        public ?string $configFile = null,
    ) {
        parent::__construct(match ($type) {
            NoerdExceptionType::AppNotAssigned => "App '{$appName}' is not assigned to this tenant",
            NoerdExceptionType::AppAccessDenied => "App '{$appName}' is not accessible for this user",
            NoerdExceptionType::ConfigNotFound => "Config file not found: {$configFile}",
        });
    }

    public function render(): Response
    {
        return response()->view('noerd::errors.noerd-error', [
            // The view branches on the raw key, not the enum case.
            'type' => $this->type->value,
            'appName' => $this->appName,
            'configFile' => $this->configFile,
        ], $this->type->isAccessDenial() ? 403 : 500);
    }
}
