<?php

namespace RefBytes\Lti\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RefBytes\Lti\Concerns\ResolvesTenant;
use RefBytes\Lti\Exceptions\LtiPlatformNotFoundException;
use RefBytes\Lti\Models\LtiPlatform;

class OidcLoginService
{
    use ResolvesTenant;

    /**
     * Handle the OIDC login initiation request from the platform.
     */
    public function handleLoginInitiation(Request $request): RedirectResponse
    {
        $issuer = $request->input('iss');
        $clientId = $request->input('client_id');
        $loginHint = $request->input('login_hint');
        $targetLinkUri = $request->input('target_link_uri');
        $ltiMessageHint = $request->input('lti_message_hint');

        $tenant = $this->resolveTenant($request);

        $platform = LtiPlatform::findByIssuerAndClientId($issuer, $clientId, $tenant);

        if (! $platform) {
            throw new LtiPlatformNotFoundException(
                "No platform registered for issuer [{$issuer}] and client_id [{$clientId}]."
            );
        }

        $state = Str::random(40);
        $nonce = Str::random(40);

        $this->cacheState($state, [
            'nonce' => $nonce,
            'platform_id' => $platform->id,
            'target_link_uri' => $targetLinkUri,
        ]);

        $params = [
            'scope' => 'openid',
            'response_type' => 'id_token',
            'response_mode' => 'form_post',
            'prompt' => 'none',
            'client_id' => $platform->client_id,
            'redirect_uri' => route('lti.launch'),
            'state' => $state,
            'nonce' => $nonce,
            'login_hint' => $loginHint,
        ];

        if ($ltiMessageHint) {
            $params['lti_message_hint'] = $ltiMessageHint;
        }

        $authUrl = $platform->auth_url.'?'.http_build_query($params);

        return redirect()->away($authUrl);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cacheState(string $state, array $data): void
    {
        $prefix = config('lti.cache_prefix', 'lti:');
        $ttl = config('lti.state_ttl', 600);

        $this->cache()->put($prefix.'state:'.$state, $data, $ttl);
    }

    private function cache(): Repository
    {
        return Cache::store(config('lti.cache_store'));
    }
}
