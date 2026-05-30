<?php

use Illuminate\Support\Facades\Http;
use RefBytes\Lti\Models\LtiPlatform;

const OPENID_CONFIG_URL = 'https://canvas.example.com/.well-known/openid-configuration';
const REGISTRATION_ENDPOINT = 'https://canvas.example.com/api/lti/registrations';
const PLATFORM_ISSUER = 'https://canvas.example.com';

/**
 * @return array<string, mixed>
 */
function platformOpenIdConfig(array $overrides = []): array
{
    return array_merge([
        'issuer' => PLATFORM_ISSUER,
        'authorization_endpoint' => 'https://canvas.example.com/api/lti/authorize_redirect',
        'token_endpoint' => 'https://canvas.example.com/login/oauth2/token',
        'jwks_uri' => 'https://canvas.example.com/api/lti/security/jwks',
        'registration_endpoint' => REGISTRATION_ENDPOINT,
        'scopes_supported' => ['openid'],
        'response_types_supported' => ['id_token'],
        'https://purl.imsglobal.org/spec/lti-platform-configuration' => [
            'product_family_code' => 'canvas',
        ],
    ], $overrides);
}

beforeEach(function () {
    config()->set('lti.tool.name', 'My Tool');
    config()->set('lti.tool.domain', 'tool.example.com');
});

it('completes a full dynamic registration flow', function () {
    Http::fake([
        OPENID_CONFIG_URL => Http::response(platformOpenIdConfig()),
        REGISTRATION_ENDPOINT => Http::response([
            'client_id' => 'generated-client-id-123',
        ]),
    ]);

    $response = $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
        'registration_token' => 'reg-token-abc',
    ]));

    $response->assertOk();

    expect(LtiPlatform::count())->toBe(1);

    $platform = LtiPlatform::first();
    expect($platform->issuer)->toBe(PLATFORM_ISSUER)
        ->and($platform->client_id)->toBe('generated-client-id-123')
        ->and($platform->auth_url)->toBe('https://canvas.example.com/api/lti/authorize_redirect')
        ->and($platform->token_url)->toBe('https://canvas.example.com/login/oauth2/token')
        ->and($platform->jwks_url)->toBe('https://canvas.example.com/api/lti/security/jwks')
        ->and($platform->name)->toBe('canvas')
        ->and($platform->version)->toBe('1.3')
        ->and($platform->registered_at)->not->toBeNull()
        ->and($platform->registration_token)->toBe('reg-token-abc');
});

it('sends correct tool metadata in the registration request', function () {
    Http::fake([
        OPENID_CONFIG_URL => Http::response(platformOpenIdConfig()),
        REGISTRATION_ENDPOINT => Http::response(['client_id' => 'cid']),
    ]);

    $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
        'registration_token' => 'tok',
    ]));

    Http::assertSent(function ($request) {
        if ($request->url() !== REGISTRATION_ENDPOINT) {
            return true;
        }

        $body = $request->data();

        return $body['initiate_login_uri'] === route('lti.login')
            && $body['redirect_uris'] === [route('lti.launch')]
            && $body['jwks_uri'] === route('lti.jwks')
            && $body['client_name'] === 'My Tool'
            && $body['token_endpoint_auth_method'] === 'private_key_jwt'
            && in_array('id_token', $body['response_types'], true)
            && str_contains($body['scope'], 'lineitem')
            && str_contains($body['scope'], 'contextmembership.readonly');
    });
});

it('includes deep linking message when enabled', function () {
    config()->set('lti.tool.deep_linking_enabled', true);
    config()->set('lti.tool.deep_linking_label', 'Insert Quiz');

    Http::fake([
        OPENID_CONFIG_URL => Http::response(platformOpenIdConfig()),
        REGISTRATION_ENDPOINT => Http::response(['client_id' => 'cid']),
    ]);

    $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
    ]));

    Http::assertSent(function ($request) {
        if ($request->url() !== REGISTRATION_ENDPOINT) {
            return true;
        }

        $claim = $request->data()['https://purl.imsglobal.org/spec/lti-tool-configuration'];

        return isset($claim['messages'])
            && $claim['messages'][0]['type'] === 'LtiDeepLinkingRequest'
            && $claim['messages'][0]['label'] === 'Insert Quiz';
    });
});

it('omits deep linking message when disabled', function () {
    config()->set('lti.tool.deep_linking_enabled', false);

    Http::fake([
        OPENID_CONFIG_URL => Http::response(platformOpenIdConfig()),
        REGISTRATION_ENDPOINT => Http::response(['client_id' => 'cid']),
    ]);

    $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
    ]));

    Http::assertSent(function ($request) {
        if ($request->url() !== REGISTRATION_ENDPOINT) {
            return true;
        }

        $claim = $request->data()['https://purl.imsglobal.org/spec/lti-tool-configuration'];

        return ! isset($claim['messages']);
    });
});

it('sends the bearer token when registration_token is present', function () {
    Http::fake([
        OPENID_CONFIG_URL => Http::response(platformOpenIdConfig()),
        REGISTRATION_ENDPOINT => Http::response(['client_id' => 'cid']),
    ]);

    $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
        'registration_token' => 'secret-bearer-token',
    ]));

    Http::assertSent(function ($request) {
        if ($request->url() !== REGISTRATION_ENDPOINT) {
            return true;
        }

        return $request->header('Authorization')[0] === 'Bearer secret-bearer-token';
    });
});

it('returns 400 when openid_configuration is missing', function () {
    $response = $this->get(route('lti.register'));

    $response->assertStatus(400);
    $response->assertJsonStructure(['error']);
});

it('returns 400 when fetching openid_configuration fails', function () {
    Http::fake([
        OPENID_CONFIG_URL => Http::response('', 500),
    ]);

    $response = $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
    ]));

    $response->assertStatus(400);
});

it('returns 400 when platform config is missing required fields', function () {
    Http::fake([
        OPENID_CONFIG_URL => Http::response(platformOpenIdConfig([
            'registration_endpoint' => '',
        ])),
    ]);

    $response = $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
    ]));

    $response->assertStatus(400);
});

it('returns 400 when the registration POST fails', function () {
    Http::fake([
        OPENID_CONFIG_URL => Http::response(platformOpenIdConfig()),
        REGISTRATION_ENDPOINT => Http::response(['error' => 'denied'], 403),
    ]);

    $response = $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
    ]));

    $response->assertStatus(400);
});

it('returns 400 when the registration response is missing client_id', function () {
    Http::fake([
        OPENID_CONFIG_URL => Http::response(platformOpenIdConfig()),
        REGISTRATION_ENDPOINT => Http::response(['other' => 'value']),
    ]);

    $response = $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
    ]));

    $response->assertStatus(400);
});

it('renders the success view with the postMessage script', function () {
    Http::fake([
        OPENID_CONFIG_URL => Http::response(platformOpenIdConfig()),
        REGISTRATION_ENDPOINT => Http::response(['client_id' => 'cid']),
    ]);

    $response = $this->get(route('lti.register', [
        'openid_configuration' => OPENID_CONFIG_URL,
    ]));

    $response->assertOk()
        ->assertSee('org.imsglobal.lti.close', escape: false)
        ->assertSee('Complete Registration');
});
