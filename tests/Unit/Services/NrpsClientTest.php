<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\DataTransferObjects\NrpsMembershipResult;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Services\NrpsClient;
use RefBytes\Lti\Services\ToolKeyService;

beforeEach(function () {
    app(ToolKeyService::class)->generateKey();

    $this->platform = LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'token_url' => 'https://canvas.example.com/login/oauth2/token',
    ]);

    $this->membershipsUrl = 'https://canvas.example.com/api/lti/courses/123/memberships';

    $this->launchData = new LtiLaunchData(
        platform: $this->platform,
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: '1',
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: 'link-1',
        userId: 'user-1',
        roles: [],
        claims: [
            'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice' => [
                'context_memberships_url' => $this->membershipsUrl,
                'service_versions' => ['2.0'],
            ],
        ],
    );

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]),
    ]);
});

it('fetches members from the memberships endpoint', function () {
    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        $this->membershipsUrl.'*' => Http::response([
            'id' => $this->membershipsUrl,
            'context' => [
                'id' => 'course-123',
                'label' => 'CS101',
                'title' => 'Intro to CS',
            ],
            'members' => [
                [
                    'user_id' => 'student-1',
                    'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership/Learner'],
                    'status' => 'Active',
                    'name' => 'Alice',
                    'email' => 'alice@example.com',
                ],
                [
                    'user_id' => 'instructor-1',
                    'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership/Instructor'],
                    'status' => 'Active',
                    'name' => 'Bob',
                ],
            ],
        ]),
    ]);

    $result = app(NrpsClient::class)->getMembers($this->launchData);

    expect($result)->toBeInstanceOf(NrpsMembershipResult::class)
        ->and($result->members)->toHaveCount(2)
        ->and($result->context->id)->toBe('course-123')
        ->and($result->context->label)->toBe('CS101')
        ->and($result->members[0]->userId)->toBe('student-1')
        ->and($result->members[0]->isLearner())->toBeTrue()
        ->and($result->members[1]->userId)->toBe('instructor-1')
        ->and($result->members[1]->isInstructor())->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), $this->membershipsUrl)
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && str_contains($request->header('Accept')[0] ?? '', 'membershipcontainer');
    });
});

it('sends role filter as query parameter', function () {
    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        $this->membershipsUrl.'*' => Http::response([
            'id' => $this->membershipsUrl,
            'members' => [],
        ]),
    ]);

    app(NrpsClient::class)->getMembers(
        $this->launchData,
        role: 'http://purl.imsglobal.org/vocab/lis/v2/membership/Learner',
    );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'role=');
    });
});

it('sends limit and rlid as query parameters', function () {
    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        $this->membershipsUrl.'*' => Http::response([
            'id' => $this->membershipsUrl,
            'members' => [],
        ]),
    ]);

    app(NrpsClient::class)->getMembers(
        $this->launchData,
        limit: 25,
        resourceLinkId: 'link-42',
    );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'limit=25')
            && str_contains($request->url(), 'rlid=link-42');
    });
});

it('follows pagination links', function () {
    $page2Url = 'https://canvas.example.com/api/lti/courses/123/memberships?page=2';
    $membershipsUrl = $this->membershipsUrl;
    $callCount = 0;

    Http::fake(function ($request) use ($page2Url, $membershipsUrl, &$callCount) {
        if (str_contains($request->url(), 'oauth2/token')) {
            return Http::response(['access_token' => 'test-token', 'expires_in' => 3600]);
        }

        $callCount++;

        // Second memberships call (page 2) — no Link header
        if ($callCount > 1) {
            return Http::response([
                'id' => $membershipsUrl,
                'members' => [
                    ['user_id' => 'user-3', 'roles' => [], 'status' => 'Active'],
                ],
            ]);
        }

        // First memberships call — includes Link header for next page
        return Http::response([
            'id' => $membershipsUrl,
            'members' => [
                ['user_id' => 'user-1', 'roles' => [], 'status' => 'Active'],
                ['user_id' => 'user-2', 'roles' => [], 'status' => 'Active'],
            ],
        ], 200, [
            'Link' => '<'.$page2Url.'>; rel="next"',
        ]);
    });

    $result = app(NrpsClient::class)->getMembers($this->launchData);

    expect($result->members)->toHaveCount(3)
        ->and($result->members[0]->userId)->toBe('user-1')
        ->and($result->members[2]->userId)->toBe('user-3');
});

it('throws when nrps claim is missing', function () {
    $launchData = new LtiLaunchData(
        platform: $this->platform,
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: '1',
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: null,
        userId: 'user-1',
        roles: [],
        claims: [],
    );

    app(NrpsClient::class)->getMembers($launchData);
})->throws(LtiException::class, 'Missing NRPS');

it('returns a lazy collection of members', function () {
    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        $this->membershipsUrl.'*' => Http::response([
            'id' => $this->membershipsUrl,
            'members' => [
                ['user_id' => 'user-1', 'roles' => [], 'status' => 'Active'],
                ['user_id' => 'user-2', 'roles' => [], 'status' => 'Active'],
            ],
        ]),
    ]);

    $lazy = app(NrpsClient::class)->getMembersLazy($this->launchData);

    expect($lazy)->toBeInstanceOf(LazyCollection::class);

    $members = $lazy->all();
    expect($members)->toHaveCount(2)
        ->and($members[0]->userId)->toBe('user-1')
        ->and($members[1]->userId)->toBe('user-2');
});
