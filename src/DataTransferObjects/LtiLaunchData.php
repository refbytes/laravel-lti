<?php

namespace RefBytes\Lti\DataTransferObjects;

use Illuminate\Support\Arr;
use RefBytes\Lti\Models\LtiPlatform;

final readonly class LtiLaunchData
{
    /**
     * @param  array<string>  $roles
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public LtiPlatform $platform,
        public string $messageType,
        public string $ltiVersion,
        public ?string $deploymentId,
        public string $targetLinkUri,
        public ?string $resourceLinkId,
        public ?string $userId,
        public array $roles,
        public array $claims,
        public ?string $launchId = null,
    ) {}

    /**
     * Access a claim by key. Supports dot-notation for nested values within a claim.
     * Exact key matches take priority over dot-notation traversal, which is important
     * because LTI claim keys are URLs that contain dots.
     */
    public function claim(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->claims)) {
            return $this->claims[$key];
        }

        return Arr::get($this->claims, $key, $default);
    }

    public function hasRole(string $roleUri): bool
    {
        return in_array($roleUri, $this->roles, true);
    }

    public function isInstructor(): bool
    {
        return $this->hasRole('http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor')
            || $this->hasRole('http://purl.imsglobal.org/vocab/lis/v2/institution/person#Instructor');
    }

    public function isLearner(): bool
    {
        return $this->hasRole('http://purl.imsglobal.org/vocab/lis/v2/membership#Learner')
            || $this->hasRole('http://purl.imsglobal.org/vocab/lis/v2/institution/person#Learner');
    }

    public function isDeepLinkingRequest(): bool
    {
        return $this->messageType === 'LtiDeepLinkingRequest';
    }

    public function deepLinkingSettings(): ?DeepLinkingSettings
    {
        if (! $this->isDeepLinkingRequest()) {
            return null;
        }

        return DeepLinkingSettings::fromClaims($this->claims);
    }
}
