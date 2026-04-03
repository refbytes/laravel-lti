<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RefBytes\Lti\Events\LtiLaunchValidated;
use RefBytes\Lti\Models\LtiLaunch;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Tests\Helpers\JwtHelper;

it('validates a full LTI launch and dispatches event', function () {
    Event::fake([LtiLaunchValidated::class]);

    $jwtHelper = new JwtHelper;

    $platform = LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'jwks_url' => 'https://canvas.example.com/jwks',
    ]);

    Http::fake([
        'https://canvas.example.com/jwks' => Http::response($jwtHelper->jwks()),
    ]);

    $state = 'integration-test-state';
    $nonce = 'integration-test-nonce';

    Cache::put('lti:state:'.$state, [
        'nonce' => $nonce,
        'platform_id' => $platform->id,
        'target_link_uri' => 'https://tool.example.com/launch',
    ], 600);

    $idToken = $jwtHelper->encode([
        'iss' => 'https://canvas.example.com',
        'aud' => '12345',
        'sub' => 'student-99',
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
        'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
        'https://purl.imsglobal.org/spec/lti/claim/deployment_id' => '1',
        'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' => 'https://tool.example.com/launch',
        'https://purl.imsglobal.org/spec/lti/claim/resource_link' => [
            'id' => 'link-42',
            'title' => 'Assignment 1',
        ],
        'https://purl.imsglobal.org/spec/lti/claim/roles' => [
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner',
        ],
    ]);

    $response = $this->post(route('lti.launch'), [
        'id_token' => $idToken,
        'state' => $state,
    ]);

    $response->assertOk();
    $response->assertJson([
        'message_type' => 'LtiResourceLinkRequest',
        'user_id' => 'student-99',
        'target_link_uri' => 'https://tool.example.com/launch',
    ]);

    Event::assertDispatched(LtiLaunchValidated::class, function ($event) {
        return $event->launch->userId === 'student-99'
            && $event->launch->isLearner();
    });
});

it('persists launch when store_launches is enabled', function () {
    config()->set('lti.store_launches', true);

    $jwtHelper = new JwtHelper;

    $platform = LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'jwks_url' => 'https://canvas.example.com/jwks',
    ]);

    Http::fake([
        'https://canvas.example.com/jwks' => Http::response($jwtHelper->jwks()),
    ]);

    $state = 'persist-test-state';
    $nonce = 'persist-test-nonce';

    Cache::put('lti:state:'.$state, [
        'nonce' => $nonce,
        'platform_id' => $platform->id,
        'target_link_uri' => 'https://tool.example.com/launch',
    ], 600);

    $idToken = $jwtHelper->encode([
        'iss' => 'https://canvas.example.com',
        'aud' => '12345',
        'sub' => 'user-1',
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
        'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
        'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' => 'https://tool.example.com/launch',
    ]);

    $response = $this->post(route('lti.launch'), [
        'id_token' => $idToken,
        'state' => $state,
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['launch_id']);

    expect(LtiLaunch::count())->toBe(1);
    expect(LtiLaunch::first())
        ->message_type->toBe('LtiResourceLinkRequest')
        ->user_id->toBe('user-1');
});

it('returns 400 for invalid state', function () {
    $response = $this->post(route('lti.launch'), [
        'id_token' => 'some-token',
        'state' => 'bad-state',
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid or expired state. The launch may have timed out.']);
});

it('returns 400 for missing parameters', function () {
    $response = $this->post(route('lti.launch'), []);

    $response->assertStatus(400);
});

it('consumes state so it cannot be reused', function () {
    $jwtHelper = new JwtHelper;

    $platform = LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'jwks_url' => 'https://canvas.example.com/jwks',
    ]);

    Http::fake([
        'https://canvas.example.com/jwks' => Http::response($jwtHelper->jwks()),
    ]);

    $state = 'one-time-state';
    $nonce = 'one-time-nonce';

    Cache::put('lti:state:'.$state, [
        'nonce' => $nonce,
        'platform_id' => $platform->id,
        'target_link_uri' => 'https://tool.example.com/launch',
    ], 600);

    $idToken = $jwtHelper->encode([
        'iss' => 'https://canvas.example.com',
        'aud' => '12345',
        'sub' => 'user-1',
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
        'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
        'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' => 'https://tool.example.com/launch',
    ]);

    // First request succeeds
    $this->post(route('lti.launch'), [
        'id_token' => $idToken,
        'state' => $state,
    ])->assertOk();

    // Second request with same state fails
    $this->post(route('lti.launch'), [
        'id_token' => $idToken,
        'state' => $state,
    ])->assertStatus(400);
});
