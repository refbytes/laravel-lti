<?php

namespace RefBytes\Lti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\LazyCollection;
use RefBytes\Lti\Contracts\ContentItem;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\DataTransferObjects\NrpsMembershipResult;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Models\LtiToolKey;
use RefBytes\Lti\Services\DeepLinkingService;
use RefBytes\Lti\Services\NrpsClient;
use RefBytes\Lti\Services\PlatformOAuth2Service;
use RefBytes\Lti\Services\ToolKeyService;

class Lti
{
    /**
     * Register a new LTI platform.
     *
     * @param  array{issuer: string, client_id: string, auth_url: string, token_url: string, jwks_url: string, deployment_id?: string, name?: string, tenant_id?: int}  $attributes
     */
    public function registerPlatform(array $attributes): LtiPlatform
    {
        return LtiPlatform::create($attributes);
    }

    /**
     * Find a platform by its issuer and client_id.
     */
    public function platformForIssuer(string $issuer, string $clientId, ?Model $tenant = null): ?LtiPlatform
    {
        return LtiPlatform::findByIssuerAndClientId($issuer, $clientId, $tenant);
    }

    /**
     * Generate a new RSA keypair for the tool.
     */
    public function generateToolKey(?Model $tenant = null): LtiToolKey
    {
        return app(ToolKeyService::class)->generateKey($tenant);
    }

    /**
     * Rotate tool keys, optionally deactivating previous ones.
     */
    public function rotateToolKeys(?Model $tenant = null, bool $deactivatePrevious = false): LtiToolKey
    {
        return app(ToolKeyService::class)->rotateKeys($tenant, $deactivatePrevious);
    }

    /**
     * Get the tool's JWKS for a tenant.
     *
     * @return array{keys: array<int, array<string, string>>}
     */
    public function toolJwks(?Model $tenant = null): array
    {
        return app(ToolKeyService::class)->toJwks($tenant);
    }

    /**
     * Sign a JWT payload using the tool's active key.
     *
     * @param  array<string, mixed>  $payload
     */
    public function signToolJwt(array $payload, ?Model $tenant = null): string
    {
        return app(ToolKeyService::class)->signJwt($payload, $tenant);
    }

    /**
     * Get an access token from a platform for service calls.
     *
     * @param  array<string>  $scopes
     */
    public function getAccessToken(LtiPlatform $platform, array $scopes, ?Model $tenant = null): string
    {
        return app(PlatformOAuth2Service::class)->getAccessToken($platform, $scopes, $tenant);
    }

    /**
     * Build a signed JWT for a deep linking response.
     *
     * @param  array<ContentItem>  $contentItems
     */
    public function buildDeepLinkingResponse(LtiLaunchData $launchData, array $contentItems, ?Model $tenant = null): string
    {
        return app(DeepLinkingService::class)->buildResponseJwt($launchData, $contentItems, $tenant);
    }

    /**
     * Build an auto-submitting form response for deep linking.
     *
     * @param  array<ContentItem>  $contentItems
     */
    public function buildDeepLinkingFormResponse(LtiLaunchData $launchData, array $contentItems, ?Model $tenant = null): Response
    {
        return app(DeepLinkingService::class)->buildFormResponse($launchData, $contentItems, $tenant);
    }

    /**
     * Get all members from the NRPS context memberships endpoint.
     */
    public function getMembers(
        LtiLaunchData $launchData,
        ?string $role = null,
        ?int $limit = null,
        ?string $resourceLinkId = null,
        ?Model $tenant = null,
    ): NrpsMembershipResult {
        return app(NrpsClient::class)->getMembers($launchData, $role, $limit, $resourceLinkId, $tenant);
    }

    /**
     * Lazily iterate members from the NRPS endpoint, fetching pages on demand.
     */
    public function getMembersLazy(
        LtiLaunchData $launchData,
        ?string $role = null,
        ?int $limit = null,
        ?string $resourceLinkId = null,
        ?Model $tenant = null,
    ): LazyCollection {
        return app(NrpsClient::class)->getMembersLazy($launchData, $role, $limit, $resourceLinkId, $tenant);
    }
}
