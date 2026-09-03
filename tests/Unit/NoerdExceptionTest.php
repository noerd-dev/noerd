<?php

declare(strict_types=1);

use Noerd\Enums\NoerdExceptionType;
use Noerd\Exceptions\NoerdException;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('renders app not assigned as a friendly 403 error page', function (): void {
    $exception = new NoerdException(
        NoerdExceptionType::AppNotAssigned,
        appName: 'CMS',
    );

    $response = $exception->render();

    // From the user's perspective this is an access problem, not a server
    // error — same friendly page as the permission denial.
    expect($response->getStatusCode())->toBe(403);
});

it('renders config not found error page', function (): void {
    $exception = new NoerdException(
        NoerdExceptionType::ConfigNotFound,
        configFile: 'details/test.yml',
    );

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(500);
});
