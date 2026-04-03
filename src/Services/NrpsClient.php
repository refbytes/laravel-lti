<?php

namespace RefBytes\Lti\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use RefBytes\Lti\Concerns\ParsesLinkHeader;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\DataTransferObjects\NrpsMember;
use RefBytes\Lti\DataTransferObjects\NrpsMembershipResult;
use RefBytes\Lti\DataTransferObjects\NrpsServiceInfo;

class NrpsClient
{
    use ParsesLinkHeader;

    public const SCOPE_MEMBERSHIP_READONLY = 'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly';

    private const CONTENT_TYPE = 'application/vnd.ims.lti-nrps.v2.membershipcontainer+json';

    public function __construct(
        private PlatformOAuth2Service $oauthService,
    ) {}

    /**
     * Fetch all members from the NRPS memberships endpoint, following pagination.
     */
    public function getMembers(
        LtiLaunchData $launchData,
        ?string $role = null,
        ?int $limit = null,
        ?string $resourceLinkId = null,
        ?Model $tenant = null,
    ): NrpsMembershipResult {
        $serviceInfo = NrpsServiceInfo::fromClaims($launchData->claims);
        $token = $this->getToken($launchData, $tenant);

        $query = $this->buildQuery($role, $limit, $resourceLinkId);
        $url = $serviceInfo->contextMembershipsUrl;

        $allMembers = [];
        $responseData = null;

        do {
            $response = Http::withToken($token)
                ->accept(self::CONTENT_TYPE)
                ->get($url, $query);

            $response->throw();

            $data = $response->json();
            $responseData ??= $data;

            foreach ($data['members'] ?? [] as $member) {
                $allMembers[] = $member;
            }

            $url = $this->parseNextLink($response->header('Link'));
            $query = []; // Subsequent pages use the full URL from the Link header
        } while ($url !== null);

        $responseData['members'] = $allMembers;

        return NrpsMembershipResult::fromResponseData($responseData);
    }

    /**
     * Lazily iterate members from the NRPS endpoint, fetching pages on demand.
     */
    public function getMembersLazy(
        LtiLaunchData $launchData,
        ?string $role = null,
        ?int $limit = null,
        ?string $resourceLinkId = null,
        ?Model $tenant = null,
    ): LazyCollection {
        return LazyCollection::make(function () use ($launchData, $role, $limit, $resourceLinkId, $tenant) {
            $serviceInfo = NrpsServiceInfo::fromClaims($launchData->claims);
            $token = $this->getToken($launchData, $tenant);

            $query = $this->buildQuery($role, $limit, $resourceLinkId);
            $url = $serviceInfo->contextMembershipsUrl;

            do {
                $response = Http::withToken($token)
                    ->accept(self::CONTENT_TYPE)
                    ->get($url, $query);

                $response->throw();

                foreach ($response->json('members', []) as $member) {
                    yield NrpsMember::fromArray($member);
                }

                $url = $this->parseNextLink($response->header('Link'));
                $query = [];
            } while ($url !== null);
        });
    }

    private function getToken(LtiLaunchData $launchData, ?Model $tenant): string
    {
        return $this->oauthService->getAccessToken(
            $launchData->platform,
            [self::SCOPE_MEMBERSHIP_READONLY],
            $tenant,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuery(?string $role, ?int $limit, ?string $resourceLinkId): array
    {
        return array_filter([
            'role' => $role,
            'limit' => $limit,
            'rlid' => $resourceLinkId,
        ], fn ($v) => $v !== null);
    }
}
