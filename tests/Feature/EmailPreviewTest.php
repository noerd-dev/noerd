<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Noerd\Mail\EmailPreviewTestMail;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | HasEmailPreview: the "send test email" button is rate limited per USER, and
 | the preview never emits raw HTML from the stored body — not even when the
 | markdown view is unavailable and the fallback path renders.
 */

it('sends exactly one test email and then sits out the cooldown', function (): void {
    Mail::fake();

    $user = NoerdUser::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('noerd-test::email-preview-test')
        ->call('sendTestEmail')
        ->call('sendTestEmail');

    Mail::assertQueued(EmailPreviewTestMail::class, 1);
    Mail::assertQueued(EmailPreviewTestMail::class, fn(EmailPreviewTestMail $mail): bool => $mail->hasTo($user->email));

    expect($component->get('canSendTestEmail'))->toBeFalse()
        ->and($component->get('testEmailCooldownSeconds'))->toBeGreaterThan(0);
});

it('scopes the cooldown to the sending user', function (): void {
    Mail::fake();

    $first = NoerdUser::factory()->create();
    $this->actingAs($first);

    Livewire::test('noerd-test::email-preview-test')->call('sendTestEmail');

    $second = NoerdUser::factory()->create();
    $this->actingAs($second);

    expect(Livewire::test('noerd-test::email-preview-test')->get('canSendTestEmail'))->toBeTrue();

    Livewire::test('noerd-test::email-preview-test')->call('sendTestEmail');

    Mail::assertQueued(EmailPreviewTestMail::class, 2);
});

it('escapes HTML from the stored body in the fallback preview', function (): void {
    $this->actingAs(NoerdUser::factory()->create());

    $html = Livewire::test('noerd-test::email-preview-test')->instance()->previewHtml();

    expect($html)->toContain('Zz body for Jane')
        ->and($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});
