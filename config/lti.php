<?php

// config for RefBytes/Lti
return [

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | Set tenant_model to your application's tenant class (e.g. App\Models\Team)
    | and tenant_resolver to a class implementing RefBytes\Lti\Contracts\TenantResolver.
    | When tenant_model is null, multi-tenancy is disabled entirely.
    |
    */

    'tenant_model' => null,

    'tenant_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configure the route prefix and middleware for the LTI endpoints.
    | Middleware is empty by default because LTI launches are cross-origin
    | POST requests that must not have CSRF verification applied.
    |
    */

    'route_prefix' => 'lti',

    'route_middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The cache store used for OIDC state/nonce and JWKS caching.
    | Set to null to use the application's default cache store.
    |
    */

    'cache_store' => null,

    'cache_prefix' => 'lti:',

    'state_ttl' => 600,

    'jwks_ttl' => 86400,

    /*
    |--------------------------------------------------------------------------
    | Launch Persistence
    |--------------------------------------------------------------------------
    |
    | When enabled, validated launches are persisted to the lti_launches table.
    |
    */

    'store_launches' => false,

    /*
    |--------------------------------------------------------------------------
    | Tool Configuration
    |--------------------------------------------------------------------------
    |
    | Metadata about this tool that platforms need during registration.
    |
    */

    'tool' => [
        'name' => env('LTI_TOOL_NAME'),
        'description' => env('LTI_TOOL_DESCRIPTION', ''),
        'domain' => env('LTI_TOOL_DOMAIN'),
        'logo_uri' => env('LTI_TOOL_LOGO_URI'),
        'client_uri' => env('LTI_TOOL_CLIENT_URI'),
        'policy_uri' => env('LTI_TOOL_POLICY_URI'),
        'tos_uri' => env('LTI_TOOL_TOS_URI'),
        'contacts' => [],
        'key_algorithm' => 'RS256',
        'key_bits' => 2048,
        'default_scopes' => [
            'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
            'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
            'https://purl.imsglobal.org/spec/lti-ags/scope/score',
            'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly',
        ],
        'deep_linking_enabled' => true,
        'deep_linking_label' => 'Add Activity',
    ],

    'tool_jwks_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | LTI 1.1 (OAuth 1.0a)
    |--------------------------------------------------------------------------
    |
    | LTI 1.1 launches are signed with OAuth 1.0a HMAC-SHA1. Used nonces are
    | cached for the TTL below to prevent replay attacks. Timestamps must be
    | within the tolerance window of the server's current time.
    |
    */

    'oauth1_nonce_ttl' => 600,

    'oauth1_timestamp_tolerance' => 300,

];
