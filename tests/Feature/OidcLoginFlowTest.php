<?php

use RefBytes\Lti\Models\LtiPlatform;

it('handles OIDC login initiation via POST', function () {
    $platform = LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'auth_url' => 'https://canvas.example.com/api/lti/authorize_redirect',
    ]);

    $response = $this->post(route('lti.login'), [
        'iss' => 'https://canvas.example.com',
        'client_id' => '12345',
        'login_hint' => 'user-hint',
        'target_link_uri' => 'https://tool.example.com/launch',
    ]);

    $response->assertRedirect();
    expect($response->getTargetUrl())
        ->toStartWith('https://canvas.example.com/api/lti/authorize_redirect');
});

it('handles OIDC login initiation via GET', function () {
    LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'auth_url' => 'https://canvas.example.com/api/lti/authorize_redirect',
    ]);

    $response = $this->get(route('lti.login', [
        'iss' => 'https://canvas.example.com',
        'client_id' => '12345',
        'login_hint' => 'user-hint',
        'target_link_uri' => 'https://tool.example.com/launch',
    ]));

    $response->assertRedirect();
});

it('returns 400 for unknown platform', function () {
    $response = $this->post(route('lti.login'), [
        'iss' => 'https://unknown.example.com',
        'client_id' => '99999',
        'login_hint' => 'hint',
        'target_link_uri' => 'https://tool.example.com/launch',
    ]);

    $response->assertStatus(400);
    $response->assertJsonStructure(['error']);
});
