<?php

namespace RefBytes\Lti\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RefBytes\Lti\Concerns\ResolvesTenant;
use RefBytes\Lti\Services\ToolKeyService;

class JwksController
{
    use ResolvesTenant;

    public function __construct(
        private ToolKeyService $toolKeyService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $jwks = $this->toolKeyService->toJwks($tenant);

        return response()->json($jwks, 200, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
