<?php

use Illuminate\Support\Facades\Http;
use RefBytes\Lti\DataTransferObjects\AgsLineItem;
use RefBytes\Lti\DataTransferObjects\AgsResult;
use RefBytes\Lti\DataTransferObjects\AgsScore;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Services\AgsClient;
use RefBytes\Lti\Services\ToolKeyService;

beforeEach(function () {
    app(ToolKeyService::class)->generateKey();

    $this->platform = LtiPlatform::factory()->create([
        'issuer' => 'https://canvas.example.com',
        'client_id' => '12345',
        'token_url' => 'https://canvas.example.com/login/oauth2/token',
    ]);

    $this->lineItemsUrl = 'https://canvas.example.com/api/lti/courses/123/line_items';

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
            'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint' => [
                'lineitems' => $this->lineItemsUrl,
                'scope' => [
                    AgsClient::SCOPE_LINE_ITEM,
                    AgsClient::SCOPE_SCORE,
                    AgsClient::SCOPE_RESULT_READONLY,
                ],
            ],
        ],
    );

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
    ]);
});

it('fetches line items', function () {
    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        $this->lineItemsUrl.'*' => Http::response([
            [
                'id' => $this->lineItemsUrl.'/1',
                'scoreMaximum' => 100,
                'label' => 'Quiz 1',
            ],
            [
                'id' => $this->lineItemsUrl.'/2',
                'scoreMaximum' => 50,
                'label' => 'Homework',
            ],
        ]),
    ]);

    $items = app(AgsClient::class)->getLineItems($this->launchData);

    expect($items)->toHaveCount(2)
        ->and($items[0])->toBeInstanceOf(AgsLineItem::class)
        ->and($items[0]->label)->toBe('Quiz 1')
        ->and($items[1]->scoreMaximum)->toBe(50.0);
});

it('gets a single line item', function () {
    $lineItemUrl = $this->lineItemsUrl.'/1';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        $lineItemUrl => Http::response([
            'id' => $lineItemUrl,
            'scoreMaximum' => 100,
            'label' => 'Final Exam',
            'tag' => 'exam',
        ]),
    ]);

    $item = app(AgsClient::class)->getLineItem($this->launchData, $lineItemUrl);

    expect($item->label)->toBe('Final Exam')
        ->and($item->tag)->toBe('exam');
});

it('creates a line item', function () {
    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        $this->lineItemsUrl => Http::response([
            'id' => $this->lineItemsUrl.'/3',
            'scoreMaximum' => 100,
            'label' => 'New Assignment',
            'tag' => 'assignment',
        ]),
    ]);

    $newItem = AgsLineItem::make('New Assignment', 100.0)->tag('assignment');
    $created = app(AgsClient::class)->createLineItem($this->launchData, $newItem);

    expect($created->id)->toBe($this->lineItemsUrl.'/3')
        ->and($created->label)->toBe('New Assignment');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'line_items')
            && str_contains($request->header('Content-Type')[0] ?? '', 'lineitem+json');
    });
});

it('updates a line item', function () {
    $lineItemUrl = $this->lineItemsUrl.'/1';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        $lineItemUrl => Http::response([
            'id' => $lineItemUrl,
            'scoreMaximum' => 200,
            'label' => 'Updated Quiz',
        ]),
    ]);

    $item = AgsLineItem::make('Updated Quiz', 200.0);
    $updated = app(AgsClient::class)->updateLineItem($this->launchData, $lineItemUrl, $item);

    expect($updated->scoreMaximum)->toBe(200.0)
        ->and($updated->label)->toBe('Updated Quiz');

    Http::assertSent(fn ($request) => $request->method() === 'PUT');
});

it('deletes a line item', function () {
    $lineItemUrl = $this->lineItemsUrl.'/1';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        $lineItemUrl => Http::response(null, 204),
    ]);

    app(AgsClient::class)->deleteLineItem($this->launchData, $lineItemUrl);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE');
});

it('submits a score', function () {
    $lineItemUrl = $this->lineItemsUrl.'/1';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        $lineItemUrl.'/scores' => Http::response(null, 200),
    ]);

    $score = AgsScore::make('student-42')
        ->scoreGiven(85.0)
        ->scoreMaximum(100.0)
        ->comment('Great work!');

    app(AgsClient::class)->submitScore($this->launchData, $lineItemUrl, $score);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/scores')
            && $request->header('Content-Type')[0] === 'application/vnd.ims.lis.v1.score+json';
    });
});

it('fetches results', function () {
    $lineItemUrl = $this->lineItemsUrl.'/1';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        $lineItemUrl.'/results*' => Http::response([
            [
                'id' => $lineItemUrl.'/results/1',
                'scoreOf' => $lineItemUrl,
                'userId' => 'student-1',
                'resultScore' => 90,
                'resultMaximum' => 100,
            ],
            [
                'id' => $lineItemUrl.'/results/2',
                'scoreOf' => $lineItemUrl,
                'userId' => 'student-2',
                'resultScore' => 75,
                'resultMaximum' => 100,
                'comment' => 'Needs improvement',
            ],
        ]),
    ]);

    $results = app(AgsClient::class)->getResults($this->launchData, $lineItemUrl);

    expect($results)->toHaveCount(2)
        ->and($results[0])->toBeInstanceOf(AgsResult::class)
        ->and($results[0]->resultScore)->toBe(90.0)
        ->and($results[1]->comment)->toBe('Needs improvement');
});

it('filters results by user_id', function () {
    $lineItemUrl = $this->lineItemsUrl.'/1';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        $lineItemUrl.'/results*' => Http::response([]),
    ]);

    app(AgsClient::class)->getResults($this->launchData, $lineItemUrl, userId: 'student-42');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'user_id=student-42');
    });
});

it('throws when ags claim is missing', function () {
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

    app(AgsClient::class)->getLineItems($launchData);
})->throws(LtiException::class, 'Missing AGS');

it('inserts /scores before the query string of a Moodle-style line item URL', function () {
    $lineItemUrl = 'https://moodle.example.com/mod/lti/services.php/2/lineitems/7/lineitem?type_id=2';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        'moodle.example.com/*' => Http::response(null, 200),
    ]);

    app(AgsClient::class)->submitScore($this->launchData, $lineItemUrl, AgsScore::make('student-42')->scoreGiven(85.0));

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://moodle.example.com/mod/lti/services.php/2/lineitems/7/lineitem/scores?type_id=2');
});

it('keeps the line item query string when fetching results', function () {
    $lineItemUrl = 'https://moodle.example.com/mod/lti/services.php/2/lineitems/7/lineitem?type_id=2';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        'moodle.example.com/*' => Http::response([]),
    ]);

    app(AgsClient::class)->getResults($this->launchData, $lineItemUrl, userId: 'student-42');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://moodle.example.com/mod/lti/services.php/2/lineitems/7/lineitem/results?')
        && $request['type_id'] === '2'
        && $request['user_id'] === 'student-42');
});

it('keeps the line item query string when lazily fetching results', function () {
    $lineItemUrl = 'https://moodle.example.com/mod/lti/services.php/2/lineitems/7/lineitem?type_id=2';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        'moodle.example.com/*' => Http::response([]),
    ]);

    app(AgsClient::class)->getResultsLazy($this->launchData, $lineItemUrl)->all();

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->url() === 'https://moodle.example.com/mod/lti/services.php/2/lineitems/7/lineitem/results?type_id=2');
});

it('keeps the query string of a paginated next link', function () {
    $lineItemUrl = $this->lineItemsUrl.'/1';
    $nextUrl = $lineItemUrl.'/results?page=2&per_page=1';

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        $nextUrl => Http::response([
            ['id' => $lineItemUrl.'/results/2', 'scoreOf' => $lineItemUrl, 'userId' => 'student-2', 'resultScore' => 75, 'resultMaximum' => 100],
        ]),
        $lineItemUrl.'/results*' => Http::response([
            ['id' => $lineItemUrl.'/results/1', 'scoreOf' => $lineItemUrl, 'userId' => 'student-1', 'resultScore' => 90, 'resultMaximum' => 100],
        ], 200, ['Link' => '<'.$nextUrl.'>; rel="next"']),
    ]);

    $results = app(AgsClient::class)->getResults($this->launchData, $lineItemUrl);

    expect($results)->toHaveCount(2);
    Http::assertSent(fn ($request) => $request->url() === $nextUrl);
});

it('keeps the query string of a Moodle-style lineitems URL when fetching line items', function () {
    $lineItemsUrl = 'https://moodle.example.com/mod/lti/services.php/2/lineitems?type_id=2';

    $launchData = new LtiLaunchData(
        platform: $this->platform,
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: '1',
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: 'link-1',
        userId: 'user-1',
        roles: [],
        claims: [
            'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint' => [
                'lineitems' => $lineItemsUrl,
                'scope' => [AgsClient::SCOPE_LINE_ITEM],
            ],
        ],
    );

    Http::fake([
        'https://canvas.example.com/login/oauth2/token' => Http::response([
            'access_token' => 'ags-token',
            'expires_in' => 3600,
        ]),
        'moodle.example.com/*' => Http::response([]),
    ]);

    app(AgsClient::class)->getLineItems($launchData, tag: 'grade');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://moodle.example.com/mod/lti/services.php/2/lineitems?')
        && $request['type_id'] === '2'
        && $request['tag'] === 'grade');
});
