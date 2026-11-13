<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Noerd\Middleware\RememberPageUrl;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | RememberPageUrl records the last full page of the `noerd` route group — the
 | page a reloaded routed modal returns to. Files, redirects, Livewire requests
 | and ?modal=true URLs never count.
 */

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Route::middleware('noerd')->group(function (): void {
        Route::get('zz-remember/page', fn() => response('<html><body>page</body></html>'));
        Route::get('zz-remember/pdf', fn() => response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']));
        Route::get('zz-remember/download', fn() => response()->streamDownload(fn() => print ('zip'), 'export.zip'));
        Route::get('zz-remember/redirect', fn() => redirect('zz-remember/page'));
    });
});

it('remembers a successful html page', function (): void {
    $this->get('zz-remember/page?view=all')->assertOk();

    expect(session(RememberPageUrl::SESSION_KEY))->toBe(url('zz-remember/page?view=all'));
});

it('keeps the remembered page when a file or download is opened', function (string $uri): void {
    $this->get('zz-remember/page')->assertOk();
    $this->get($uri)->assertOk();

    expect(session(RememberPageUrl::SESSION_KEY))->toBe(url('zz-remember/page'));
})->with([
    'pdf' => 'zz-remember/pdf',
    'streamed download' => 'zz-remember/download',
]);

it('does not remember a redirect, a modal url or a livewire request', function (): void {
    $this->get('zz-remember/redirect')->assertRedirect();
    $this->get('zz-remember/page?modal=true')->assertOk();
    $this->withHeader('X-Livewire', '1')->get('zz-remember/page')->assertOk();

    expect(session(RememberPageUrl::SESSION_KEY))->toBeNull();
});
