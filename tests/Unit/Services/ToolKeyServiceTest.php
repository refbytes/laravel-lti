<?php

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Models\LtiToolKey;
use RefBytes\Lti\Services\ToolKeyService;

beforeEach(function () {
    $this->service = app(ToolKeyService::class);
});

it('generates an RSA keypair and persists to database', function () {
    $key = $this->service->generateKey();

    expect($key)->toBeInstanceOf(LtiToolKey::class)
        ->and($key->exists)->toBeTrue()
        ->and($key->kid)->not->toBeEmpty()
        ->and($key->algorithm)->toBe('RS256')
        ->and($key->is_active)->toBeTrue()
        ->and($key->public_key)->toContain('BEGIN PUBLIC KEY');
});

it('returns the most recent active key as signing key', function () {
    $key1 = $this->service->generateKey();
    $key2 = $this->service->generateKey();

    $signingKey = $this->service->getSigningKey();

    expect($signingKey->id)->toBe($key2->id);
});

it('throws when no active key exists', function () {
    $this->service->getSigningKey();
})->throws(LtiException::class, 'No active tool key found');

it('only returns active non-expired keys', function () {
    $active = $this->service->generateKey();
    LtiToolKey::factory()->inactive()->create();
    LtiToolKey::factory()->expired()->create();

    $keys = $this->service->getActiveKeys();

    expect($keys)->toHaveCount(1)
        ->and($keys->first()->id)->toBe($active->id);
});

it('builds a valid JWKS structure', function () {
    $this->service->generateKey();
    $this->service->generateKey();

    $jwks = $this->service->toJwks();

    expect($jwks)->toHaveKey('keys')
        ->and($jwks['keys'])->toHaveCount(2);

    $firstKey = $jwks['keys'][0];
    expect($firstKey)
        ->toHaveKey('kty', 'RSA')
        ->toHaveKey('alg', 'RS256')
        ->toHaveKey('use', 'sig')
        ->toHaveKey('kid')
        ->toHaveKey('n')
        ->toHaveKey('e');
});

it('signs a JWT that can be verified with the public key', function () {
    $this->service->generateKey();

    $payload = ['iss' => 'test', 'sub' => 'user-1', 'iat' => time(), 'exp' => time() + 300];
    $token = $this->service->signJwt($payload);

    $jwks = $this->service->toJwks();
    $keys = JWK::parseKeySet($jwks);
    $decoded = JWT::decode($token, $keys);

    expect($decoded->iss)->toBe('test')
        ->and($decoded->sub)->toBe('user-1');
});

it('deactivates a key by kid', function () {
    $key = $this->service->generateKey();

    $this->service->deactivateKey($key->kid);

    expect($key->fresh()->is_active)->toBeFalse();
});

it('rotates keys and optionally deactivates previous', function () {
    $oldKey = $this->service->generateKey();

    $newKey = $this->service->rotateKeys(null, true);

    expect($oldKey->fresh()->is_active)->toBeFalse()
        ->and($newKey->is_active)->toBeTrue()
        ->and($newKey->id)->not->toBe($oldKey->id);
});

it('rotates keys without deactivating previous by default', function () {
    $oldKey = $this->service->generateKey();

    $newKey = $this->service->rotateKeys();

    expect($oldKey->fresh()->is_active)->toBeTrue()
        ->and($newKey->is_active)->toBeTrue();
});
