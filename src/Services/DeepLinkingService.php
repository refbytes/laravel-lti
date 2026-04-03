<?php

namespace RefBytes\Lti\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RefBytes\Lti\Contracts\ContentItem;
use RefBytes\Lti\DataTransferObjects\DeepLinkingSettings;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;

class DeepLinkingService
{
    public function __construct(
        private ToolKeyService $toolKeyService,
    ) {}

    /**
     * Build a signed JWT for the deep linking response.
     *
     * @param  array<ContentItem>  $contentItems
     */
    public function buildResponseJwt(LtiLaunchData $launchData, array $contentItems, ?Model $tenant = null): string
    {
        $settings = DeepLinkingSettings::fromClaims($launchData->claims);
        $now = time();

        $payload = [
            'iss' => $launchData->platform->client_id,
            'aud' => $launchData->platform->issuer,
            'iat' => $now,
            'exp' => $now + 300,
            'nonce' => Str::uuid()->toString(),
            'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiDeepLinkingResponse',
            'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
            'https://purl.imsglobal.org/spec/lti/claim/deployment_id' => $launchData->deploymentId,
            'https://purl.imsglobal.org/spec/lti-dl/claim/content_items' => array_map(
                fn (ContentItem $item) => $item->toArray(),
                $contentItems,
            ),
        ];

        if ($settings->data !== null) {
            $payload['https://purl.imsglobal.org/spec/lti-dl/claim/data'] = $settings->data;
        }

        return $this->toolKeyService->signJwt($payload, $tenant);
    }

    /**
     * Build an auto-submitting form response that POSTs the JWT to the platform.
     *
     * @param  array<ContentItem>  $contentItems
     */
    public function buildFormResponse(LtiLaunchData $launchData, array $contentItems, ?Model $tenant = null): Response
    {
        $jwt = $this->buildResponseJwt($launchData, $contentItems, $tenant);
        $settings = DeepLinkingSettings::fromClaims($launchData->claims);
        $returnUrl = $settings->deepLinkReturnUrl;

        return response()->view('lti::deep-linking-auto-post', [
            'jwt' => $jwt,
            'returnUrl' => $returnUrl,
        ]);
    }
}
