<?php

namespace Noerd\Exceptions;

use Exception;
use Illuminate\Http\Response;

class NoerdException extends Exception
{
    public const TYPE_APP_NOT_ASSIGNED = 'app_not_assigned';

    public const TYPE_CONFIG_NOT_FOUND = 'config_not_found';

    public function __construct(
        public string $type,
        public ?string $appName = null,
        public ?string $configFile = null,
    ) {
        $message = match ($type) {
            self::TYPE_APP_NOT_ASSIGNED => "App '{$appName}' is not assigned to this tenant",
            self::TYPE_CONFIG_NOT_FOUND => "Config file not found: {$configFile}",
            default => 'Unknown error',
        };
        parent::__construct($message);
    }

    public function render(): Response
    {
        return response()->view('noerd::errors.noerd-error', [
            'type' => $this->type,
            'appName' => $this->appName,
            'configFile' => $this->configFile,
        ], 500);
    }
}
