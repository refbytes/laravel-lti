<?php

namespace RefBytes\Lti\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Models\LtiPlatform;

class PlatformOAuth2Service
{
    public function __construct(
        private ToolKeyService $toolKeyService,
    ) {}

    /**
     * Obtain an access token from a platform using the client_credentials grant.
     *
     * @param  array<string>  $scopes
     */
    public function getAccessToken(LtiPlatform $platform, array $scopes, ?Model $tenant = null): string
    {
        $scopeString = implode(' ', $scopes);
        $cacheKey = $this->tokenCacheKey($platform, $scopeString);

        $cached = $this->cache()->get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $assertion = $this->buildClientAssertion($platform, $tenant);

        $response = Http::throw()->asForm()->post($platform->token_url, [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $assertion,
            'scope' => $scopeString,
        ]);

        $data = $response->json();

        if (! isset($data['access_token'])) {
            throw new LtiException('Platform token endpoint did not return an access_token.');
        }

        $ttl = max(($data['expires_in'] ?? 3600) - 30, 60);
        $this->cache()->put($cacheKey, $data['access_token'], $ttl);

        return $data['access_token'];
    }

    private function buildClientAssertion(LtiPlatform $platform, ?Model $tenant): string
    {
        $now = time();

        return $this->toolKeyService->signJwt([
            'iss' => $platform->client_id,
            'sub' => $platform->client_id,
            'aud' => $platform->token_url,
            'jti' => Str::uuid()->toString(),
            'iat' => $now,
            'exp' => $now + 60,
        ], $tenant);
    }

    private function tokenCacheKey(LtiPlatform $platform, string $scopes): string
    {
        $prefix = config('lti.cache_prefix', 'lti:');

        return $prefix.'token:'.$platform->id.':'.hash('sha256', $scopes);
    }

    private function cache(): Repository
    {
        return Cache::store(config('lti.cache_store'));
    }
}
