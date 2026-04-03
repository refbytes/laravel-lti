<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Exceptions\LtiJwtException;
use RefBytes\Lti\Exceptions\LtiStateException;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Services\LaunchValidationService;
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
});

it('validates a launch successfully', function () {
    $state = 'test-state-value';
    $nonce = 'test-nonce-value';

    Cache::put('lti:state:'.$state, [
        'nonce' => $nonce,
        'platform_id' => $this->platform->id,
        'target_link_uri' => 'https://tool.example.com/launch',
    ], 600);

    $idToken = $this->jwtHelper->encode([
        'iss' => 'https://canvas.example.com',
        'aud' => '12345',
        'sub' => 'user-42',
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
        'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
        'https://purl.imsglobal.org/spec/lti/claim/deployment_id' => '1',
        'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' => 'https://tool.example.com/launch',
        'https://purl.imsglobal.org/spec/lti/claim/resource_link' => [
            'id' => 'resource-link-1',
        ],
        'https://purl.imsglobal.org/spec/lti/claim/roles' => [
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor',
        ],
    ]);

    $request = Request::create('/lti/launch', 'POST', [
        'id_token' => $idToken,
        'state' => $state,
    ]);

    $service = app(LaunchValidationService::class);
    $result = $service->validateLaunch($request);

    expect($result)
        ->toBeInstanceOf(LtiLaunchData::class)
        ->and($result->messageType)->toBe('LtiResourceLinkRequest')
        ->and($result->ltiVersion)->toBe('1.3.0')
        ->and($result->userId)->toBe('user-42')
        ->and($result->resourceLinkId)->toBe('resource-link-1')
        ->and($result->deploymentId)->toBe('1')
        ->and($result->roles)->toContain('http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor')
        ->and($result->isInstructor())->toBeTrue();
});

it('throws on missing state parameter', function () {
    $request = Request::create('/lti/launch', 'POST', [
        'id_token' => 'some-token',
    ]);

    $service = app(LaunchValidationService::class);
    $service->validateLaunch($request);
})->throws(LtiStateException::class, 'Missing id_token or state');

it('throws on expired or invalid state', function () {
    $request = Request::create('/lti/launch', 'POST', [
        'id_token' => 'some-token',
        'state' => 'nonexistent-state',
    ]);

    $service = app(LaunchValidationService::class);
    $service->validateLaunch($request);
})->throws(LtiStateException::class, 'Invalid or expired state');

it('throws on nonce mismatch', function () {
    $state = 'test-state';
    Cache::put('lti:state:'.$state, [
        'nonce' => 'expected-nonce',
        'platform_id' => $this->platform->id,
        'target_link_uri' => 'https://tool.example.com/launch',
    ], 600);

    $idToken = $this->jwtHelper->encode([
        'iss' => 'https://canvas.example.com',
        'aud' => '12345',
        'sub' => 'user-1',
        'nonce' => 'wrong-nonce',
        'iat' => time(),
        'exp' => time() + 300,
        'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
    ]);

    $request = Request::create('/lti/launch', 'POST', [
        'id_token' => $idToken,
        'state' => $state,
    ]);

    $service = app(LaunchValidationService::class);
    $service->validateLaunch($request);
})->throws(LtiJwtException::class, 'Nonce mismatch');

it('throws on audience mismatch', function () {
    $state = 'test-state';
    $nonce = 'test-nonce';
    Cache::put('lti:state:'.$state, [
        'nonce' => $nonce,
        'platform_id' => $this->platform->id,
        'target_link_uri' => 'https://tool.example.com/launch',
    ], 600);

    $idToken = $this->jwtHelper->encode([
        'iss' => 'https://canvas.example.com',
        'aud' => 'wrong-client-id',
        'sub' => 'user-1',
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
        'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
    ]);

    $request = Request::create('/lti/launch', 'POST', [
        'id_token' => $idToken,
        'state' => $state,
    ]);

    $service = app(LaunchValidationService::class);
    $service->validateLaunch($request);
})->throws(LtiJwtException::class, 'Audience does not contain');

it('supports audience as array', function () {
    $state = 'test-state';
    $nonce = 'test-nonce';
    Cache::put('lti:state:'.$state, [
        'nonce' => $nonce,
        'platform_id' => $this->platform->id,
        'target_link_uri' => 'https://tool.example.com/launch',
    ], 600);

    $idToken = $this->jwtHelper->encode([
        'iss' => 'https://canvas.example.com',
        'aud' => ['12345', 'other-client'],
        'sub' => 'user-1',
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
        'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
        'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' => 'https://tool.example.com/launch',
    ]);

    $request = Request::create('/lti/launch', 'POST', [
        'id_token' => $idToken,
        'state' => $state,
    ]);

    $service = app(LaunchValidationService::class);
    $result = $service->validateLaunch($request);

    expect($result)->toBeInstanceOf(LtiLaunchData::class);
});
