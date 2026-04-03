<?php

namespace RefBytes\Lti\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RefBytes\Lti\Contracts\TenantResolver;

trait ResolvesTenant
{
    protected function resolveTenant(Request $request): ?Model
    {
        $resolverClass = config('lti.tenant_resolver');

        if (! $resolverClass || ! config('lti.tenant_model')) {
            return null;
        }

        /** @var TenantResolver $resolver */
        $resolver = app($resolverClass);

        return $resolver->resolve($request);
    }
}
