<?php

namespace RefBytes\Lti\DataTransferObjects;

use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Services\AgsClient;

final readonly class AgsServiceInfo
{
    public const CLAIM_KEY = 'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint';

    /**
     * @param  array<string>  $scopes
     */
    public function __construct(
        public ?string $lineItemsUrl,
        public ?string $lineItemUrl,
        public array $scopes,
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims): self
    {
        $ags = $claims[self::CLAIM_KEY] ?? null;

        if (! is_array($ags)) {
            throw new LtiException('Missing AGS endpoint claim in LTI launch.');
        }

        return new self(
            lineItemsUrl: $ags['lineitems'] ?? null,
            lineItemUrl: $ags['lineitem'] ?? null,
            scopes: $ags['scope'] ?? [],
        );
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function canManageLineItems(): bool
    {
        return $this->hasScope(AgsClient::SCOPE_LINE_ITEM);
    }

    public function canReadLineItems(): bool
    {
        return $this->hasScope(AgsClient::SCOPE_LINE_ITEM)
            || $this->hasScope(AgsClient::SCOPE_LINE_ITEM_READONLY);
    }

    public function canPostScores(): bool
    {
        return $this->hasScope(AgsClient::SCOPE_SCORE);
    }

    public function canReadResults(): bool
    {
        return $this->hasScope(AgsClient::SCOPE_RESULT_READONLY);
    }
}
