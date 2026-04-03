<?php

use Illuminate\Support\Facades\Http;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Services\PlatformOAuth2Service;
use RefBytes\Lti\Services\ToolKeyService;

beforeEach(function () {
    app(ToolKeyService::class)->generateKey();

    $this->platform = LtiPlatform::factory()->create([
        'client_id' => '12345',
        'token_url' => 'https://canvas.example.com/login/oauth2/token',
    ]);
});

it('exchanges a signed JWT for an access token', function () {
    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]),
    ]);

    $service = app(PlatformOAuth2Service::class);
    $token = $service->getAccessToken($this->platform, [
        'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
    ]);

    expect($token)->toBe('test-access-token');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://canvas.example.com/login/oauth2/token'
            && $request['grant_type'] === 'client_credentials'
            && $request['client_assertion_type'] === 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer'
            && str_contains($request['scope'], 'lineitem');
    });
});

it('caches the access token', function () {
    $requestCount = 0;

    Http::fake(function () use (&$requestCount) {
        $requestCount++;

        return Http::response([
            'access_token' => 'cached-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);
    });

    $service = app(PlatformOAuth2Service::class);

    $token1 = $service->getAccessToken($this->platform, ['scope1']);
    $token2 = $service->getAccessToken($this->platform, ['scope1']);

    expect($token1)->toBe('cached-token')
        ->and($token2)->toBe('cached-token')
        ->and($requestCount)->toBe(1);
});

it('includes requested scopes in the token request', function () {
    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'token',
            'expires_in' => 3600,
        ]),
    ]);

    $service = app(PlatformOAuth2Service::class);
    $service->getAccessToken($this->platform, [
        'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
        'https://purl.imsglobal.org/spec/lti-ags/scope/score',
    ]);

    Http::assertSent(function ($request) {
        $scope = $request['scope'];

        return str_contains($scope, 'lineitem') && str_contains($scope, 'score');
    });
});
