<?php

namespace RefBytes\Lti\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use RefBytes\Lti\Concerns\ParsesLinkHeader;
use RefBytes\Lti\DataTransferObjects\AgsLineItem;
use RefBytes\Lti\DataTransferObjects\AgsResult;
use RefBytes\Lti\DataTransferObjects\AgsScore;
use RefBytes\Lti\DataTransferObjects\AgsServiceInfo;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Exceptions\LtiException;

class AgsClient
{
    use ParsesLinkHeader;

    public const SCOPE_LINE_ITEM = 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem';

    public const SCOPE_LINE_ITEM_READONLY = 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem.readonly';

    public const SCOPE_SCORE = 'https://purl.imsglobal.org/spec/lti-ags/scope/score';

    public const SCOPE_RESULT_READONLY = 'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly';

    private const CONTENT_TYPE_LINE_ITEM = 'application/vnd.ims.lis.v2.lineitem+json';

    private const CONTENT_TYPE_LINE_ITEM_CONTAINER = 'application/vnd.ims.lis.v2.lineitemcontainer+json';

    private const CONTENT_TYPE_SCORE = 'application/vnd.ims.lis.v2.score+json';

    private const CONTENT_TYPE_RESULT_CONTAINER = 'application/vnd.ims.lis.v2.resultcontainer+json';

    public function __construct(
        private PlatformOAuth2Service $oauthService,
    ) {}

    /**
     * List all line items, following pagination.
     *
     * @return array<AgsLineItem>
     */
    public function getLineItems(
        LtiLaunchData $launchData,
        ?string $resourceId = null,
        ?string $tag = null,
        ?string $resourceLinkId = null,
        ?int $limit = null,
        ?Model $tenant = null,
    ): array {
        $serviceInfo = $this->getServiceInfo($launchData);

        if (! $serviceInfo->lineItemsUrl) {
            throw new LtiException('No lineitems URL available in AGS claim.');
        }

        $token = $this->getToken($launchData, [self::SCOPE_LINE_ITEM, self::SCOPE_LINE_ITEM_READONLY], $tenant);

        $query = array_filter([
            'resource_id' => $resourceId,
            'tag' => $tag,
            'resource_link_id' => $resourceLinkId,
            'limit' => $limit,
        ], fn ($v) => $v !== null);

        $url = $serviceInfo->lineItemsUrl;
        $items = [];

        do {
            $response = Http::withToken($token)
                ->accept(self::CONTENT_TYPE_LINE_ITEM_CONTAINER)
                ->get($url, $query);

            $response->throw();

            foreach ($response->json() as $item) {
                $items[] = AgsLineItem::fromArray($item);
            }

            $url = $this->parseNextLink($response->header('Link'));
            $query = [];
        } while ($url !== null);

        return $items;
    }

    /**
     * Get a single line item by URL.
     */
    public function getLineItem(LtiLaunchData $launchData, string $lineItemUrl, ?Model $tenant = null): AgsLineItem
    {
        $token = $this->getToken($launchData, [self::SCOPE_LINE_ITEM, self::SCOPE_LINE_ITEM_READONLY], $tenant);

        $response = Http::withToken($token)
            ->accept(self::CONTENT_TYPE_LINE_ITEM)
            ->get($lineItemUrl);

        $response->throw();

        return AgsLineItem::fromArray($response->json());
    }

    /**
     * Create a new line item.
     */
    public function createLineItem(LtiLaunchData $launchData, AgsLineItem $lineItem, ?Model $tenant = null): AgsLineItem
    {
        $serviceInfo = $this->getServiceInfo($launchData);

        if (! $serviceInfo->lineItemsUrl) {
            throw new LtiException('No lineitems URL available in AGS claim.');
        }

        $token = $this->getToken($launchData, [self::SCOPE_LINE_ITEM], $tenant);

        $response = Http::withToken($token)
            ->contentType(self::CONTENT_TYPE_LINE_ITEM)
            ->post($serviceInfo->lineItemsUrl, $lineItem->toArray());

        $response->throw();

        return AgsLineItem::fromArray($response->json());
    }

    /**
     * Update an existing line item.
     */
    public function updateLineItem(LtiLaunchData $launchData, string $lineItemUrl, AgsLineItem $lineItem, ?Model $tenant = null): AgsLineItem
    {
        $token = $this->getToken($launchData, [self::SCOPE_LINE_ITEM], $tenant);

        $response = Http::withToken($token)
            ->contentType(self::CONTENT_TYPE_LINE_ITEM)
            ->put($lineItemUrl, $lineItem->toArray());

        $response->throw();

        return AgsLineItem::fromArray($response->json());
    }

    /**
     * Delete a line item.
     */
    public function deleteLineItem(LtiLaunchData $launchData, string $lineItemUrl, ?Model $tenant = null): void
    {
        $token = $this->getToken($launchData, [self::SCOPE_LINE_ITEM], $tenant);

        Http::withToken($token)->delete($lineItemUrl)->throw();
    }

    /**
     * Submit a score for a user on a line item.
     */
    public function submitScore(LtiLaunchData $launchData, string $lineItemUrl, AgsScore $score, ?Model $tenant = null): void
    {
        $token = $this->getToken($launchData, [self::SCOPE_SCORE], $tenant);

        $response = Http::withToken($token)
            ->contentType(self::CONTENT_TYPE_SCORE)
            ->post(rtrim($lineItemUrl, '/').'/scores', $score->toArray());

        $response->throw();
    }

    /**
     * Get results for a line item, following pagination.
     *
     * @return array<AgsResult>
     */
    public function getResults(
        LtiLaunchData $launchData,
        string $lineItemUrl,
        ?string $userId = null,
        ?int $limit = null,
        ?Model $tenant = null,
    ): array {
        $token = $this->getToken($launchData, [self::SCOPE_RESULT_READONLY], $tenant);

        $query = array_filter([
            'user_id' => $userId,
            'limit' => $limit,
        ], fn ($v) => $v !== null);

        $url = rtrim($lineItemUrl, '/').'/results';
        $results = [];

        do {
            $response = Http::withToken($token)
                ->accept(self::CONTENT_TYPE_RESULT_CONTAINER)
                ->get($url, $query);

            $response->throw();

            foreach ($response->json() as $result) {
                $results[] = AgsResult::fromArray($result);
            }

            $url = $this->parseNextLink($response->header('Link'));
            $query = [];
        } while ($url !== null);

        return $results;
    }

    /**
     * Lazily iterate results for a line item, fetching pages on demand.
     */
    public function getResultsLazy(
        LtiLaunchData $launchData,
        string $lineItemUrl,
        ?string $userId = null,
        ?int $limit = null,
        ?Model $tenant = null,
    ): LazyCollection {
        return LazyCollection::make(function () use ($launchData, $lineItemUrl, $userId, $limit, $tenant) {
            $token = $this->getToken($launchData, [self::SCOPE_RESULT_READONLY], $tenant);

            $query = array_filter([
                'user_id' => $userId,
                'limit' => $limit,
            ], fn ($v) => $v !== null);

            $url = rtrim($lineItemUrl, '/').'/results';

            do {
                $response = Http::withToken($token)
                    ->accept(self::CONTENT_TYPE_RESULT_CONTAINER)
                    ->get($url, $query);

                $response->throw();

                foreach ($response->json() as $result) {
                    yield AgsResult::fromArray($result);
                }

                $url = $this->parseNextLink($response->header('Link'));
                $query = [];
            } while ($url !== null);
        });
    }

    private function getServiceInfo(LtiLaunchData $launchData): AgsServiceInfo
    {
        return AgsServiceInfo::fromClaims($launchData->claims);
    }

    /**
     * @param  array<string>  $scopes
     */
    private function getToken(LtiLaunchData $launchData, array $scopes, ?Model $tenant): string
    {
        return $this->oauthService->getAccessToken($launchData->platform, $scopes, $tenant);
    }
}
