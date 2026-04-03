<?php

namespace RefBytes\Lti\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface TenantResolver
{
    public function resolve(Request $request): ?Model;
}
