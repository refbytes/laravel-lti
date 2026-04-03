<?php

namespace RefBytes\Lti\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use RefBytes\Lti\DataTransferObjects\DeepLinkingSettings;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;

class LtiDeepLinkingRequested
{
    use Dispatchable;

    public function __construct(
        public readonly LtiLaunchData $launch,
        public readonly DeepLinkingSettings $settings,
        public readonly Request $request,
    ) {}
}
