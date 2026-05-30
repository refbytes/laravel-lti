<?php

namespace RefBytes\Lti\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Services\DynamicRegistrationService;
use Symfony\Component\HttpFoundation\Response;

class DynamicRegistrationController
{
    public function __construct(
        private DynamicRegistrationService $dynamicRegistrationService,
    ) {}

    public function __invoke(Request $request): View|Response
    {
        try {
            return $this->dynamicRegistrationService->handleRegistration($request);
        } catch (LtiException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
