<?php

use Noerd\Tests\Traits\CreatesSetupUser;

uses(Tests\TestCase::class);
uses(CreatesSetupUser::class);

it('returns 404 for /setup-collection-definitions in yaml mode', function (): void {
    config(['noerd.collections.mode' => 'yaml']);
    config(['noerd.collections.show_definitions_ui' => false]);

    ['user' => $user] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    $response = $this->get('/setup-collection-definitions');
    $response->assertNotFound();
});

it('returns 200 for /setup-collection-definitions in database mode', function (): void {
    config(['noerd.collections.mode' => 'database']);
    config(['noerd.collections.show_definitions_ui' => true]);

    ['user' => $user] = $this->createUserWithSetupAccess();
    $this->actingAs($user);

    $response = $this->get('/setup-collection-definitions');
    $response->assertOk();
});
