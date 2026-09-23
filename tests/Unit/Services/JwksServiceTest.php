<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RefBytes\Lti\Exceptions\LtiJwtException;
use RefBytes\Lti\Services\JwksService;
use RefBytes\Lti\Tests\Helpers\JwtHelper;

beforeEach(function () {
    $this->jwksService = new JwksService;
    $this->jwtHelper = new JwtHelper;
});

it('fetches and caches a JWKS', function () {
    $jwksUrl = 'https://platform.example.com/jwks';

    Http::fake([
        $jwksUrl => Http::response($this->jwtHelper->jwks()),
    ]);

    $keys = $this->jwksService->getKeySet($jwksUrl);

    expect($keys)->toBeArray()->not->toBeEmpty();

    // Second call should hit cache, not HTTP
    Http::fake([
        $jwksUrl => Http::response([], 500),
    ]);

    $cachedKeys = $this->jwksService->getKeySet($jwksUrl);
    expect($cachedKeys)->toEqual($keys);
});

it('caches the JWKS in a store that serializes values', function () {
    $jwksUrl = 'https://platform.example.com/jwks';

    config()->set('lti.cache_store', 'file');
    Cache::store('file')->flush();

    Http::fake([
        $jwksUrl => Http::response($this->jwtHelper->jwks()),
    ]);

    $this->jwksService->getKeySet($jwksUrl);
    $cachedKeys = $this->jwksService->getKeySet($jwksUrl);

    $token = $this->jwtHelper->encode(['sub' => 'user-1']);

    expect($cachedKeys[$this->jwtHelper->kid])->toBeInstanceOf(Key::class)
        ->and(JWT::decode($token, $cachedKeys)->sub)->toBe('user-1');

    Http::assertSentCount(1);
});

it('clears cached JWKS', function () {
    $jwksUrl = 'https://platform.example.com/jwks';
    $requestCount = 0;

    Http::fake(function ($request) use ($jwksUrl, &$requestCount) {
        if ($request->url() === $jwksUrl) {
            $requestCount++;

            return Http::response($this->jwtHelper->jwks());
        }
    });

    $this->jwksService->getKeySet($jwksUrl);
    expect($requestCount)->toBe(1);

    $this->jwksService->clearCache($jwksUrl);

    // After clearing, it should fetch again
    $keys = $this->jwksService->getKeySet($jwksUrl);
    expect($keys)->toBeArray()->not->toBeEmpty();
    expect($requestCount)->toBe(2);
});

it('throws on invalid JWKS response', function () {
    $jwksUrl = 'https://platform.example.com/jwks';

    Http::fake([
        $jwksUrl => Http::response(['invalid' => 'data']),
    ]);

    $this->jwksService->getKeySet($jwksUrl);
})->throws(LtiJwtException::class);
