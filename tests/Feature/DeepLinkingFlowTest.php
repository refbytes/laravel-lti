<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RefBytes\Lti\Events\LtiDeepLinkingRequested;
use RefBytes\Lti\Events\LtiLaunchValidated;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Services\ToolKeyService;
use RefBytes\Lti\Tests\Helpers\JwtHelper;

beforeEach(function () {
    $this->jwtHelper = new JwtHelper;

    $this->platform = LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'jwks_url' => 'https://canvas.example.com/jwks',
    ]);

    Http::fake([
        'https://canvas.example.com/jwks' => Http::response($this->jwtHelper->jwks()),
    ]);

    app(ToolKeyService::class)->generateKey();
});

it('dispatches LtiDeepLinkingRequested for deep linking launches', function () {
    Event::fake([LtiDeepLinkingRequested::class, LtiLaunchValidated::class]);

    $state = 'dl-test-state';
    $nonce = 'dl-test-nonce';

    Cache::put('lti:state:'.$state, [
        'nonce' => $nonce,
        'platform_id' => $this->platform->id,
        'target_link_uri' => 'https://tool.example.com/deep-link',
    ], 600);

    $idToken = $this->jwtHelper->encode([
        'iss' => 'https://canvas.example.com',
        'aud' => '12345',
        'sub' => 'instructor-1',
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
        'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiDeepLinkingRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
        'https://purl.imsglobal.org/spec/lti/claim/deployment_id' => '1',
        'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings' => [
            'deep_link_return_url' => 'https://canvas.example.com/deep_link_return',
            'accept_types' => ['ltiResourceLink', 'link'],
            'accept_multiple' => true,
            'data' => 'platform-context-data',
        ],
    ]);

    $response = $this->post(route('lti.launch'), [
        'id_token' => $idToken,
        'state' => $state,
    ]);

    $response->assertOk();
    $response->assertJson([
        'message_type' => 'LtiDeepLinkingRequest',
        'deep_link_return_url' => 'https://canvas.example.com/deep_link_return',
        'accept_types' => ['ltiResourceLink', 'link'],
        'accept_multiple' => true,
    ]);

    Event::assertDispatched(LtiDeepLinkingRequested::class, function ($event) {
        return $event->launch->messageType === 'LtiDeepLinkingRequest'
            && $event->settings->deepLinkReturnUrl === 'https://canvas.example.com/deep_link_return'
            && $event->settings->data === 'platform-context-data';
    });

    Event::assertNotDispatched(LtiLaunchValidated::class);
});

it('still dispatches LtiLaunchValidated for resource link launches', function () {
    Event::fake([LtiDeepLinkingRequested::class, LtiLaunchValidated::class]);

    $state = 'rl-test-state';
    $nonce = 'rl-test-nonce';

    Cache::put('lti:state:'.$state, [
        'nonce' => $nonce,
        'platform_id' => $this->platform->id,
        'target_link_uri' => 'https://tool.example.com/launch',
    ], 600);

    $idToken = $this->jwtHelper->encode([
        'iss' => 'https://canvas.example.com',
        'aud' => '12345',
        'sub' => 'student-1',
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

    Event::assertDispatched(LtiLaunchValidated::class);
    Event::assertNotDispatched(LtiDeepLinkingRequested::class);
});
