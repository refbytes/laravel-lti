<?php

namespace RefBytes\Lti;

use Illuminate\Database\Eloquent\Model;
use RefBytes\Lti\Models\LtiPlatform;

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
}
