<?php

namespace RefBytes\Lti\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RefBytes\Lti\Exceptions\LtiJwtException;

class JwksService
{
    /**
     * Fetch and cache a platform's JSON Web Key Set.
     *
     * The raw JWKS array is cached rather than the parsed keys: parsed `Key`
     * objects wrap an `OpenSSLAsymmetricKey`, which cannot be serialized, so
     * caching them fails on any store other than `array`.
     *
     * @return array<string, Key>
     */
    public function getKeySet(string $jwksUrl): array
    {
        $cacheKey = $this->cacheKey($jwksUrl);
        $ttl = config('lti.jwks_ttl', 86400);

        $jwks = $this->cache()->remember($cacheKey, $ttl, function () use ($jwksUrl) {
            $response = Http::throw()->get($jwksUrl);

            $jwks = $response->json();

            if (! is_array($jwks) || ! isset($jwks['keys'])) {
                throw new LtiJwtException("Invalid JWKS response from: {$jwksUrl}");
            }

            return $jwks;
        });

        return JWK::parseKeySet($jwks);
    }

    public function clearCache(string $jwksUrl): void
    {
        $this->cache()->forget($this->cacheKey($jwksUrl));
    }

    private function cacheKey(string $jwksUrl): string
    {
        $prefix = config('lti.cache_prefix', 'lti:');

        return $prefix.'jwks:'.hash('sha256', $jwksUrl);
    }

    private function cache(): Repository
    {
        return Cache::store(config('lti.cache_store'));
    }
}
