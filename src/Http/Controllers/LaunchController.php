<?php

namespace RefBytes\Lti\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RefBytes\Lti\DataTransferObjects\DeepLinkingSettings;
use RefBytes\Lti\Events\LtiDeepLinkingRequested;
use RefBytes\Lti\Events\LtiLaunchValidated;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Services\LaunchValidationService;

class LaunchController
{
    public function __construct(
        private LaunchValidationService $launchValidationService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $launchData = $this->launchValidationService->validateLaunch($request);

            if ($launchData->isDeepLinkingRequest()) {
                $settings = DeepLinkingSettings::fromClaims($launchData->claims);

                LtiDeepLinkingRequested::dispatch($launchData, $settings, $request);

                return response()->json([
                    'launch_id' => $launchData->launchId,
                    'message_type' => $launchData->messageType,
                    'deep_link_return_url' => $settings->deepLinkReturnUrl,
                    'accept_types' => $settings->acceptTypes,
                    'accept_multiple' => $settings->acceptMultiple,
                ]);
            }

            LtiLaunchValidated::dispatch($launchData, $request);

            return response()->json([
                'launch_id' => $launchData->launchId,
                'message_type' => $launchData->messageType,
                'target_link_uri' => $launchData->targetLinkUri,
                'user_id' => $launchData->userId,
                'roles' => $launchData->roles,
            ]);
        } catch (LtiException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
