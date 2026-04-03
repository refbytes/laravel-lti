<?php

namespace RefBytes\Lti\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Services\OidcLoginService;
use Symfony\Component\HttpFoundation\Response;

class OidcLoginController
{
    public function __construct(
        private OidcLoginService $oidcLoginService,
    ) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        try {
            return $this->oidcLoginService->handleLoginInitiation($request);
        } catch (LtiException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
