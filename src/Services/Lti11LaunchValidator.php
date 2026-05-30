<?php

namespace RefBytes\Lti\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RefBytes\Lti\Concerns\ResolvesTenant;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Exceptions\LtiOAuth1Exception;
use RefBytes\Lti\Exceptions\LtiPlatformNotFoundException;
use RefBytes\Lti\Models\LtiPlatform;

/**
 * Validates LTI 1.1 (OAuth 1.0a) launches and builds the unified
 * `LtiLaunchData` DTO. Mirrors the public surface of `LaunchValidationService`
 * for 1.3 — both produce the same DTO and fire the same event.
 */
class Lti11LaunchValidator
{
    use ResolvesTenant;

    public function __construct(
        private OAuth1Signer $signer,
    ) {}

    public function validate(Request $request): LtiLaunchData
    {
        $params = $request->post();

        $consumerKey = $params['oauth_consumer_key'] ?? null;
        if (! $consumerKey) {
            throw new LtiOAuth1Exception('Missing oauth_consumer_key.');
        }

        $tenant = $this->resolveTenant($request);
        $platform = LtiPlatform::findByConsumerKey($consumerKey, $tenant);

        if (! $platform) {
            throw new LtiPlatformNotFoundException(
                "No platform registered for consumer key [{$consumerKey}]."
            );
        }

        if (empty($platform->shared_secret)) {
            throw new LtiOAuth1Exception('Platform has no shared_secret configured.');
        }

        $this->assertFreshTimestamp($params['oauth_timestamp'] ?? null);
        $this->assertUnusedNonce($params['oauth_nonce'] ?? null);
        $this->assertSupportedSignatureMethod($params['oauth_signature_method'] ?? null);
        $this->assertValidSignature($request, $params, $platform->shared_secret);

        return $this->buildLaunchData($params, $platform);
    }

    private function assertFreshTimestamp(?string $timestamp): void
    {
        if ($timestamp === null || ! ctype_digit($timestamp)) {
            throw new LtiOAuth1Exception('Missing or invalid oauth_timestamp.');
        }

        $tolerance = (int) config('lti.oauth1_timestamp_tolerance', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            throw new LtiOAuth1Exception('OAuth timestamp is outside the allowed tolerance window.');
        }
    }

    private function assertUnusedNonce(?string $nonce): void
    {
        if (! $nonce) {
            throw new LtiOAuth1Exception('Missing oauth_nonce.');
        }

        $key = config('lti.cache_prefix', 'lti:').'oauth1_nonce:'.$nonce;
        $ttl = (int) config('lti.oauth1_nonce_ttl', 600);

        if ($this->cache()->has($key)) {
            throw new LtiOAuth1Exception('OAuth nonce has already been used (replay detected).');
        }

        $this->cache()->put($key, true, $ttl);
    }

    private function assertSupportedSignatureMethod(?string $method): void
    {
        if ($method !== OAuth1Signer::SIGNATURE_METHOD) {
            throw new LtiOAuth1Exception(
                'Unsupported oauth_signature_method. Only HMAC-SHA1 is supported.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function assertValidSignature(Request $request, array $params, string $sharedSecret): void
    {
        $url = route('lti.launch');
        $valid = $this->signer->verify($request->method(), $url, $params, $sharedSecret);

        if (! $valid) {
            throw new LtiOAuth1Exception('OAuth signature verification failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function buildLaunchData(array $params, LtiPlatform $platform): LtiLaunchData
    {
        $rolesRaw = $params['roles'] ?? '';
        $roles = array_values(array_filter(array_map('trim', explode(',', $rolesRaw))));

        return new LtiLaunchData(
            platform: $platform,
            messageType: $params['lti_message_type'] ?? 'basic-lti-launch-request',
            ltiVersion: $params['lti_version'] ?? 'LTI-1p0',
            deploymentId: null,
            targetLinkUri: route('lti.launch'),
            resourceLinkId: $params['resource_link_id'] ?? null,
            userId: $params['user_id'] ?? null,
            roles: $roles,
            claims: $params,
        );
    }

    private function cache(): Repository
    {
        return Cache::store(config('lti.cache_store'));
    }
}
