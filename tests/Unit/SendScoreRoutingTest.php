<?php

use Illuminate\Support\Facades\Http;
use RefBytes\Lti\DataTransferObjects\AgsServiceInfo;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Exceptions\LtiFeatureNotSupportedException;
use RefBytes\Lti\Facades\Lti;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Services\AgsClient;
use RefBytes\Lti\Services\ToolKeyService;

const LINE_ITEM_URL = 'https://canvas.example.com/lineitems/42';
const TOKEN_URL = 'https://canvas.example.com/oauth2/token';

beforeEach(function () {
    app(ToolKeyService::class)->generateKey();
});

function lti13LaunchWithAgs(array $agsOverrides = []): LtiLaunchData
{
    $platform = LtiPlatform::factory()->create([
        'token_url' => TOKEN_URL,
    ]);

    $ags = array_merge([
        'lineitem' => LINE_ITEM_URL,
        'scope' => [AgsClient::SCOPE_SCORE],
    ], $agsOverrides);

    return new LtiLaunchData(
        platform: $platform,
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: '1',
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: 'rlink-1',
        userId: 'student-1',
        roles: [],
        claims: [
            AgsServiceInfo::CLAIM_KEY => $ags,
        ],
    );
}

function lti11LaunchForRouting(array $claimOverrides = []): LtiLaunchData
{
    $platform = LtiPlatform::factory()->create([
        'version' => 'LTI-1p0',
        'issuer' => null,
        'auth_url' => null,
        'token_url' => null,
        'jwks_url' => null,
        'client_id' => 'consumer-route',
        'shared_secret' => 'secret-route',
    ]);

    $claims = array_merge([
        'lis_outcome_service_url' => 'https://lms.example.com/outcomes',
        'lis_result_sourcedid' => 'sourced-route',
    ], $claimOverrides);

    return new LtiLaunchData(
        platform: $platform,
        messageType: 'basic-lti-launch-request',
        ltiVersion: 'LTI-1p0',
        deploymentId: null,
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: 'r',
        userId: 'student-2',
        roles: ['Learner'],
        claims: $claims,
    );
}

it('routes 1.3 launches to AgsClient (POSTs to the lineitem scores URL)', function () {
    Http::fake([
        TOKEN_URL => Http::response(['access_token' => 'tok', 'token_type' => 'Bearer', 'expires_in' => 3600]),
        LINE_ITEM_URL.'/scores' => Http::response('', 200),
    ]);

    Lti::sendScore(lti13LaunchWithAgs(), 85.0, 100.0);

    Http::assertSent(fn ($r) => $r->url() === LINE_ITEM_URL.'/scores');
});

it('routes 1.1 launches to BasicOutcomesClient (POSTs to the outcome service URL)', function () {
    $successXml = <<<'XML'
        <imsx_POXEnvelopeResponse>
          <imsx_POXHeader><imsx_POXResponseHeaderInfo><imsx_statusInfo>
            <imsx_codeMajor>success</imsx_codeMajor>
          </imsx_statusInfo></imsx_POXResponseHeaderInfo></imsx_POXHeader>
        </imsx_POXEnvelopeResponse>
        XML;

    Http::fake([
        'https://lms.example.com/outcomes' => Http::response($successXml),
    ]);

    Lti::sendScore(lti11LaunchForRouting(), 92.0, 100.0);

    Http::assertSent(fn ($r) => $r->url() === 'https://lms.example.com/outcomes'
        && str_contains($r->body(), '<replaceResultRequest>')
        && str_contains($r->body(), '<textString>0.9200</textString>'));
});

it('throws LtiFeatureNotSupportedException when 1.3 launch has no AGS claim', function () {
    $platform = LtiPlatform::factory()->create();

    $launch = new LtiLaunchData(
        platform: $platform,
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: '1',
        targetLinkUri: 'x',
        resourceLinkId: 'r',
        userId: 'u',
        roles: [],
        claims: [],
    );

    expect(fn () => Lti::sendScore($launch, 1.0, 1.0))
        ->toThrow(LtiFeatureNotSupportedException::class);
});

it('throws LtiFeatureNotSupportedException when 1.3 launch has AGS claim but no lineitem URL', function () {
    $launch = lti13LaunchWithAgs(['lineitem' => null]);

    expect(fn () => Lti::sendScore($launch, 1.0, 1.0))
        ->toThrow(LtiFeatureNotSupportedException::class, 'no lineitem URL');
});

it('throws LtiFeatureNotSupportedException when 1.1 launch is missing outcome URL', function () {
    $launch = lti11LaunchForRouting(['lis_outcome_service_url' => null]);

    expect(fn () => Lti::sendScore($launch, 1.0, 1.0))
        ->toThrow(LtiFeatureNotSupportedException::class);
});
