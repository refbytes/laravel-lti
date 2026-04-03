<?php

namespace RefBytes\Lti\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
