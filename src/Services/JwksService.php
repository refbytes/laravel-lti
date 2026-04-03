<?php

namespace RefBytes\Lti\Services;

use Firebase\JWT\JWK;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RefBytes\Lti\Exceptions\LtiJwtException;

class JwksService
{
    /**
     * Fetch and cache a platform's JSON Web Key Set.
     *
     * @return array<string, \OpenSSLAsymmetricKey>
     */
    public function getKeySet(string $jwksUrl): array
    {
        $cacheKey = $this->cacheKey($jwksUrl);
        $ttl = config('lti.jwks_ttl', 86400);

        return $this->cache()->remember($cacheKey, $ttl, function () use ($jwksUrl) {
            $response = Http::throw()->get($jwksUrl);

            $jwks = $response->json();

            if (! is_array($jwks) || ! isset($jwks['keys'])) {
                throw new LtiJwtException("Invalid JWKS response from: {$jwksUrl}");
            }

            return JWK::parseKeySet($jwks);
        });
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
