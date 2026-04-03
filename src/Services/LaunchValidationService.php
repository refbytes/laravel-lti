<?php

namespace RefBytes\Lti\Services;

use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Exceptions\LtiJwtException;
use RefBytes\Lti\Exceptions\LtiPlatformNotFoundException;
use RefBytes\Lti\Exceptions\LtiStateException;
use RefBytes\Lti\Models\LtiLaunch;
use RefBytes\Lti\Models\LtiPlatform;

class LaunchValidationService
{
    private const LTI_MESSAGE_TYPE_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/message_type';

    private const LTI_VERSION_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/version';

    private const LTI_DEPLOYMENT_ID_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/deployment_id';

    private const LTI_TARGET_LINK_URI_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/target_link_uri';

    private const LTI_RESOURCE_LINK_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/resource_link';

    private const LTI_ROLES_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/roles';

    public function __construct(
        private JwksService $jwksService,
    ) {}

    /**
     * Validate the LTI launch callback and return structured launch data.
     */
    public function validateLaunch(Request $request): LtiLaunchData
    {
        $idToken = $request->input('id_token');
        $state = $request->input('state');

        if (! $idToken || ! $state) {
            throw new LtiStateException('Missing id_token or state parameter.');
        }

        $stateData = $this->pullState($state);

        if (! $stateData) {
            throw new LtiStateException('Invalid or expired state. The launch may have timed out.');
        }

        $platform = LtiPlatform::find($stateData['platform_id']);

        if (! $platform) {
            throw new LtiPlatformNotFoundException('Platform no longer exists.');
        }

        $claims = $this->decodeAndValidateJwt($idToken, $platform);

        $this->validateClaims($claims, $platform, $stateData['nonce']);

        $launchData = $this->buildLaunchData($claims, $platform);

        if (config('lti.store_launches')) {
            $launch = $this->persistLaunch($launchData, $platform);
            $launchData = new LtiLaunchData(
                platform: $launchData->platform,
                messageType: $launchData->messageType,
                ltiVersion: $launchData->ltiVersion,
                deploymentId: $launchData->deploymentId,
                targetLinkUri: $launchData->targetLinkUri,
                resourceLinkId: $launchData->resourceLinkId,
                userId: $launchData->userId,
                roles: $launchData->roles,
                claims: $launchData->claims,
                launchId: $launch->id,
            );
        }

        return $launchData;
    }

    /**
     * Decode and validate the JWT signature, retrying once on failure to handle key rotation.
     *
     * @return array<string, mixed>
     */
    private function decodeAndValidateJwt(string $idToken, LtiPlatform $platform): array
    {
        try {
            return $this->decodeJwt($idToken, $platform);
        } catch (LtiJwtException) {
            $this->jwksService->clearCache($platform->jwks_url);

            return $this->decodeJwt($idToken, $platform);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwt(string $idToken, LtiPlatform $platform): array
    {
        try {
            $keys = $this->jwksService->getKeySet($platform->jwks_url);
            $decoded = JWT::decode($idToken, $keys);

            return json_decode(json_encode($decoded), true);
        } catch (\Exception $e) {
            throw new LtiJwtException('JWT validation failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function validateClaims(array $claims, LtiPlatform $platform, string $expectedNonce): void
    {
        $actualIssuer = $claims['iss'] ?? 'null';
        if ($actualIssuer !== $platform->issuer) {
            throw new LtiJwtException(
                "Issuer mismatch. Expected [{$platform->issuer}], got [{$actualIssuer}]."
            );
        }

        $audience = $claims['aud'] ?? null;
        $validAudience = is_array($audience)
            ? in_array($platform->client_id, $audience, true)
            : $audience === $platform->client_id;

        if (! $validAudience) {
            throw new LtiJwtException('Audience does not contain the expected client_id.');
        }

        if (($claims['nonce'] ?? null) !== $expectedNonce) {
            throw new LtiJwtException('Nonce mismatch.');
        }

        if (! isset($claims[self::LTI_MESSAGE_TYPE_CLAIM])) {
            throw new LtiJwtException('Missing LTI message type claim.');
        }

        if (($claims[self::LTI_VERSION_CLAIM] ?? null) !== '1.3.0') {
            throw new LtiJwtException('Unsupported LTI version. Expected 1.3.0.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function buildLaunchData(array $claims, LtiPlatform $platform): LtiLaunchData
    {
        $resourceLink = $claims[self::LTI_RESOURCE_LINK_CLAIM] ?? null;

        return new LtiLaunchData(
            platform: $platform,
            messageType: $claims[self::LTI_MESSAGE_TYPE_CLAIM],
            ltiVersion: $claims[self::LTI_VERSION_CLAIM],
            deploymentId: $claims[self::LTI_DEPLOYMENT_ID_CLAIM] ?? null,
            targetLinkUri: $claims[self::LTI_TARGET_LINK_URI_CLAIM] ?? '',
            resourceLinkId: is_array($resourceLink) ? ($resourceLink['id'] ?? null) : null,
            userId: $claims['sub'] ?? null,
            roles: $claims[self::LTI_ROLES_CLAIM] ?? [],
            claims: $claims,
        );
    }

    private function persistLaunch(LtiLaunchData $data, LtiPlatform $platform): LtiLaunch
    {
        return LtiLaunch::create([
            'platform_id' => $platform->id,
            'tenant_id' => $platform->tenant_id,
            'message_type' => $data->messageType,
            'lti_version' => $data->ltiVersion,
            'resource_link_id' => $data->resourceLinkId,
            'target_link_uri' => $data->targetLinkUri,
            'user_id' => $data->userId,
            'roles' => $data->roles,
            'claims' => $data->claims,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pullState(string $state): ?array
    {
        $prefix = config('lti.cache_prefix', 'lti:');
        $key = $prefix.'state:'.$state;
        $data = $this->cache()->get($key);
        $this->cache()->forget($key);

        return $data;
    }

    private function cache(): Repository
    {
        return Cache::store(config('lti.cache_store'));
    }
}
