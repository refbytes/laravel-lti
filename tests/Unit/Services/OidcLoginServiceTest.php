<?php

use Illuminate\Http\Request;
use RefBytes\Lti\Exceptions\LtiPlatformNotFoundException;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Services\OidcLoginService;

it('redirects to platform auth url with correct parameters', function () {
    $platform = LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'auth_url' => 'https://canvas.example.com/api/lti/authorize_redirect',
    ]);

    $request = Request::create('/lti/login', 'POST', [
        'iss' => 'https://canvas.example.com',
        'client_id' => '12345',
        'login_hint' => 'user-hint-abc',
        'target_link_uri' => 'https://tool.example.com/launch',
    ]);

    $service = app(OidcLoginService::class);
    $response = $service->handleLoginInitiation($request);

    expect($response->getStatusCode())->toBe(302);

    $redirectUrl = $response->getTargetUrl();
    expect($redirectUrl)->toStartWith('https://canvas.example.com/api/lti/authorize_redirect');

    $query = [];
    parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $query);

    expect($query)
        ->toHaveKey('scope', 'openid')
        ->toHaveKey('response_type', 'id_token')
        ->toHaveKey('response_mode', 'form_post')
        ->toHaveKey('prompt', 'none')
        ->toHaveKey('client_id', '12345')
        ->toHaveKey('login_hint', 'user-hint-abc')
        ->toHaveKey('state')
        ->toHaveKey('nonce')
        ->toHaveKey('redirect_uri');
});

it('includes lti_message_hint when provided', function () {
    LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'auth_url' => 'https://canvas.example.com/api/lti/authorize_redirect',
    ]);

    $request = Request::create('/lti/login', 'POST', [
        'iss' => 'https://canvas.example.com',
        'client_id' => '12345',
        'login_hint' => 'hint',
        'target_link_uri' => 'https://tool.example.com/launch',
        'lti_message_hint' => 'message-hint-value',
    ]);

    $service = app(OidcLoginService::class);
    $response = $service->handleLoginInitiation($request);

    $query = [];
    parse_str(parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('lti_message_hint', 'message-hint-value');
});

it('throws when platform is not found', function () {
    $request = Request::create('/lti/login', 'POST', [
        'iss' => 'https://unknown.example.com',
        'client_id' => '99999',
        'login_hint' => 'hint',
        'target_link_uri' => 'https://tool.example.com/launch',
    ]);

    $service = app(OidcLoginService::class);
    $service->handleLoginInitiation($request);
})->throws(LtiPlatformNotFoundException::class);
