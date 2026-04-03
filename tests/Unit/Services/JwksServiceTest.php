<?php

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
