<?php

use RefBytes\Lti\Models\LtiToolKey;
use RefBytes\Lti\Services\ToolKeyService;

it('returns empty JWKS when no keys exist', function () {
    $response = $this->get(route('lti.jwks'));

    $response->assertOk();
    $response->assertJson(['keys' => []]);
});

it('serves active public keys as JWKS JSON', function () {
    app(ToolKeyService::class)->generateKey();

    $response = $this->get(route('lti.jwks'));

    $response->assertOk();

    $data = $response->json();
    expect($data['keys'])->toHaveCount(1);

    $jwk = $data['keys'][0];
    expect($jwk)
        ->toHaveKey('kty', 'RSA')
        ->toHaveKey('alg', 'RS256')
        ->toHaveKey('use', 'sig')
        ->toHaveKey('kid')
        ->toHaveKey('n')
        ->toHaveKey('e');
});

it('excludes inactive and expired keys', function () {
    app(ToolKeyService::class)->generateKey();
    LtiToolKey::factory()->inactive()->create();
    LtiToolKey::factory()->expired()->create();

    $response = $this->get(route('lti.jwks'));

    $response->assertOk();
    expect($response->json('keys'))->toHaveCount(1);
});

it('returns cache control headers', function () {
    $response = $this->get(route('lti.jwks'));

    $response->assertOk();
    $response->assertHeader('Cache-Control');
});
