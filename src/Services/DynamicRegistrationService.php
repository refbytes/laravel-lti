<?php

namespace RefBytes\Lti\Services;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RefBytes\Lti\Concerns\ResolvesTenant;
use RefBytes\Lti\DataTransferObjects\PlatformConfiguration;
use RefBytes\Lti\DataTransferObjects\ToolConfiguration;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Models\LtiPlatform;

class DynamicRegistrationService
{
    use ResolvesTenant;

    /**
     * Handle an LTI 1.3 Dynamic Registration initiation request.
     *
     * Fetches the platform's OpenID Configuration, POSTs a registration
     * request to its registration endpoint, and persists the resulting
     * platform record. Returns a view that finalizes the flow by signaling
     * the LMS to close the registration window.
     */
    public function handleRegistration(Request $request): View
    {
        $openidConfigUrl = $request->input('openid_configuration');
        $registrationToken = $request->input('registration_token');

        if (! $openidConfigUrl) {
            throw new LtiException('Missing required query parameter: openid_configuration');
        }

        $tenant = $this->resolveTenant($request);

        $platformConfig = $this->fetchPlatformConfiguration($openidConfigUrl);

        $toolConfig = ToolConfiguration::build(
            initiateLoginUri: route('lti.login'),
            launchUri: route('lti.launch'),
            jwksUri: route('lti.jwks'),
        );

        $registrationResponse = $this->postRegistration(
            $platformConfig->registrationEndpoint,
            $toolConfig->body,
            $registrationToken,
        );

        $platform = LtiPlatform::create([
            'tenant_id' => $tenant?->getKey(),
            'issuer' => $platformConfig->issuer,
            'client_id' => $registrationResponse['client_id'],
            'auth_url' => $platformConfig->authorizationEndpoint,
            'token_url' => $platformConfig->tokenEndpoint,
            'jwks_url' => $platformConfig->jwksUri,
            'name' => $platformConfig->productName(),
            'version' => '1.3',
            'registered_at' => now(),
            'registration_token' => $registrationToken,
        ]);

        return view('lti::registration-complete', [
            'platform' => $platform,
        ]);
    }

    private function fetchPlatformConfiguration(string $openidConfigUrl): PlatformConfiguration
    {
        try {
            $response = Http::acceptJson()->get($openidConfigUrl)->throw();
        } catch (RequestException $e) {
            throw new LtiException(
                "Failed to fetch platform OpenID configuration from {$openidConfigUrl}: ".$e->getMessage(),
                previous: $e,
            );
        }

        return PlatformConfiguration::fromArray($response->json());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postRegistration(string $registrationEndpoint, array $body, ?string $registrationToken): array
    {
        $request = Http::acceptJson()->asJson();

        if ($registrationToken) {
            $request = $request->withToken($registrationToken);
        }

        try {
            $response = $request->post($registrationEndpoint, $body)->throw();
        } catch (RequestException $e) {
            throw new LtiException(
                'Platform rejected registration request: '.$e->getMessage(),
                previous: $e,
            );
        }

        $data = $response->json();

        if (empty($data['client_id'])) {
            throw new LtiException('Platform registration response is missing required field: client_id');
        }

        return $data;
    }
}
