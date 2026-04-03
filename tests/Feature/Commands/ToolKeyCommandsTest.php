<?php

use RefBytes\Lti\Models\LtiToolKey;
use RefBytes\Lti\Services\ToolKeyService;

it('generates a key via artisan command', function () {
    $this->artisan('lti:generate-key')
        ->expectsOutputToContain('Generated new key')
        ->assertSuccessful();

    expect(LtiToolKey::count())->toBe(1);
    expect(LtiToolKey::first()->is_active)->toBeTrue();
});

it('rotates keys with --deactivate-previous', function () {
    app(ToolKeyService::class)->generateKey();

    $this->artisan('lti:generate-key --deactivate-previous')
        ->expectsOutputToContain('Previous keys deactivated')
        ->assertSuccessful();

    expect(LtiToolKey::where('is_active', true)->count())->toBe(1);
    expect(LtiToolKey::where('is_active', false)->count())->toBe(1);
});

it('lists keys in table format', function () {
    $key = app(ToolKeyService::class)->generateKey();

    $this->artisan('lti:list-keys')
        ->expectsOutputToContain($key->kid)
        ->assertSuccessful();
});

it('shows message when no keys exist', function () {
    $this->artisan('lti:list-keys')
        ->expectsOutputToContain('No keys found')
        ->assertSuccessful();
});

it('deactivates a key by kid', function () {
    $key = app(ToolKeyService::class)->generateKey();

    $this->artisan("lti:deactivate-key {$key->kid}")
        ->expectsOutputToContain('has been deactivated')
        ->assertSuccessful();

    expect($key->fresh()->is_active)->toBeFalse();
});
