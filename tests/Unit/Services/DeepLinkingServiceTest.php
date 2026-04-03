<?php

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RefBytes\Lti\DataTransferObjects\ContentItems\LtiResourceLinkItem;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Services\DeepLinkingService;
use RefBytes\Lti\Services\ToolKeyService;

beforeEach(function () {
    app(ToolKeyService::class)->generateKey();

    $this->platform = LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
    ]);
});

it('builds a valid deep linking response JWT', function () {
    $launchData = new LtiLaunchData(
        platform: $this->platform,
        messageType: 'LtiDeepLinkingRequest',
        ltiVersion: '1.3.0',
        deploymentId: 'deploy-1',
        targetLinkUri: '',
        resourceLinkId: null,
        userId: 'user-1',
        roles: [],
        claims: [
            'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings' => [
                'deep_link_return_url' => 'https://canvas.example.com/deep_link_return',
                'accept_types' => ['ltiResourceLink'],
                'data' => 'opaque-platform-data',
            ],
        ],
    );

    $contentItems = [
        LtiResourceLinkItem::make('https://tool.example.com/launch/1')->title('Item 1'),
    ];

    $service = app(DeepLinkingService::class);
    $jwt = $service->buildResponseJwt($launchData, $contentItems);

    // Decode and verify the JWT structure
    $toolJwks = app(ToolKeyService::class)->toJwks();
    $keys = JWK::parseKeySet($toolJwks);
    $decoded = JWT::decode($jwt, $keys);

    expect($decoded->iss)->toBe('12345')
        ->and($decoded->aud)->toBe('https://canvas.example.com')
        ->and($decoded->{'https://purl.imsglobal.org/spec/lti/claim/message_type'})->toBe('LtiDeepLinkingResponse')
        ->and($decoded->{'https://purl.imsglobal.org/spec/lti/claim/version'})->toBe('1.3.0')
        ->and($decoded->{'https://purl.imsglobal.org/spec/lti/claim/deployment_id'})->toBe('deploy-1')
        ->and($decoded->{'https://purl.imsglobal.org/spec/lti-dl/claim/data'})->toBe('opaque-platform-data');

    $items = $decoded->{'https://purl.imsglobal.org/spec/lti-dl/claim/content_items'};
    expect($items)->toHaveCount(1);
    expect($items[0]->type)->toBe('ltiResourceLink');
    expect($items[0]->title)->toBe('Item 1');
});

it('omits data claim when not provided', function () {
    $launchData = new LtiLaunchData(
        platform: $this->platform,
        messageType: 'LtiDeepLinkingRequest',
        ltiVersion: '1.3.0',
        deploymentId: 'deploy-1',
        targetLinkUri: '',
        resourceLinkId: null,
        userId: 'user-1',
        roles: [],
        claims: [
            'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings' => [
                'deep_link_return_url' => 'https://canvas.example.com/return',
                'accept_types' => ['ltiResourceLink'],
            ],
        ],
    );

    $service = app(DeepLinkingService::class);
    $jwt = $service->buildResponseJwt($launchData, []);

    $toolJwks = app(ToolKeyService::class)->toJwks();
    $keys = JWK::parseKeySet($toolJwks);
    $decoded = (array) JWT::decode($jwt, $keys);

    expect($decoded)->not->toHaveKey('https://purl.imsglobal.org/spec/lti-dl/claim/data');
});

it('builds a form response with the view', function () {
    $launchData = new LtiLaunchData(
        platform: $this->platform,
        messageType: 'LtiDeepLinkingRequest',
        ltiVersion: '1.3.0',
        deploymentId: 'deploy-1',
        targetLinkUri: '',
        resourceLinkId: null,
        userId: 'user-1',
        roles: [],
        claims: [
            'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings' => [
                'deep_link_return_url' => 'https://canvas.example.com/deep_link_return',
                'accept_types' => ['ltiResourceLink'],
            ],
        ],
    );

    $service = app(DeepLinkingService::class);
    $response = $service->buildFormResponse($launchData, [
        LtiResourceLinkItem::make('https://tool.example.com/launch'),
    ]);

    expect($response->getStatusCode())->toBe(200);

    $content = $response->getContent();
    expect($content)->toContain('https://canvas.example.com/deep_link_return')
        ->and($content)->toContain('name="JWT"')
        ->and($content)->toContain('submit()');
});
