<?php

namespace RefBytes\Lti\DataTransferObjects;

use RefBytes\Lti\Exceptions\LtiException;

final readonly class NrpsServiceInfo
{
    public const CLAIM_KEY = 'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice';

    /**
     * @param  array<string>  $serviceVersions
     */
    public function __construct(
        public string $contextMembershipsUrl,
        public array $serviceVersions,
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims): self
    {
        $nrps = $claims[self::CLAIM_KEY] ?? null;

        if (! is_array($nrps)) {
            throw new LtiException('Missing NRPS namesroleservice claim in LTI launch.');
        }

        return new self(
            contextMembershipsUrl: $nrps['context_memberships_url'] ?? throw new LtiException('Missing context_memberships_url in NRPS claim.'),
            serviceVersions: $nrps['service_versions'] ?? [],
        );
    }

    public function supportsVersion(string $version): bool
    {
        return in_array($version, $this->serviceVersions, true);
    }
}
