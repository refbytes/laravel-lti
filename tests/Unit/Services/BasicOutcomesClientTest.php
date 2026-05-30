<?php

use Illuminate\Support\Facades\Http;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Exceptions\LtiFeatureNotSupportedException;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Services\BasicOutcomesClient;

const OUTCOME_URL = 'https://lms.example.com/outcomes';

function successResponseXml(): string
{
    return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <imsx_POXEnvelopeResponse xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
          <imsx_POXHeader>
            <imsx_POXResponseHeaderInfo>
              <imsx_statusInfo>
                <imsx_codeMajor>success</imsx_codeMajor>
                <imsx_description>Score recorded.</imsx_description>
              </imsx_statusInfo>
            </imsx_POXResponseHeaderInfo>
          </imsx_POXHeader>
          <imsx_POXBody><replaceResultResponse/></imsx_POXBody>
        </imsx_POXEnvelopeResponse>
        XML;
}

function lti11LaunchForOutcomes(array $claimOverrides = []): LtiLaunchData
{
    $platform = LtiPlatform::factory()->create([
        'version' => 'LTI-1p0',
        'issuer' => null,
        'auth_url' => null,
        'token_url' => null,
        'jwks_url' => null,
        'client_id' => 'consumer-bos',
        'shared_secret' => 'secret-bos',
    ]);

    $claims = array_merge([
        'lis_outcome_service_url' => OUTCOME_URL,
        'lis_result_sourcedid' => 'sourced-1',
    ], $claimOverrides);

    return new LtiLaunchData(
        platform: $platform,
        messageType: 'basic-lti-launch-request',
        ltiVersion: 'LTI-1p0',
        deploymentId: null,
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: 'r1',
        userId: 'student-1',
        roles: ['Learner'],
        claims: $claims,
    );
}

it('posts a well-formed replaceResult XML envelope to the outcome URL', function () {
    Http::fake([
        OUTCOME_URL => Http::response(successResponseXml()),
    ]);

    $launch = lti11LaunchForOutcomes();

    app(BasicOutcomesClient::class)->replaceResult($launch, 85.0, 100.0);

    Http::assertSent(function ($request) {
        $body = $request->body();

        return $request->url() === OUTCOME_URL
            && str_contains($body, '<imsx_POXEnvelopeRequest')
            && str_contains($body, '<replaceResultRequest>')
            && str_contains($body, '<sourcedId>sourced-1</sourcedId>')
            && str_contains($body, '<textString>0.8500</textString>');
    });
});

it('includes an OAuth Authorization header with oauth_body_hash', function () {
    Http::fake([
        OUTCOME_URL => Http::response(successResponseXml()),
    ]);

    $launch = lti11LaunchForOutcomes();
    app(BasicOutcomesClient::class)->replaceResult($launch, 100.0, 100.0);

    Http::assertSent(function ($request) {
        $auth = $request->header('Authorization')[0] ?? '';

        return str_starts_with($auth, 'OAuth ')
            && str_contains($auth, 'oauth_consumer_key="consumer-bos"')
            && str_contains($auth, 'oauth_signature_method="HMAC-SHA1"')
            && str_contains($auth, 'oauth_body_hash=')
            && str_contains($auth, 'oauth_signature=');
    });
});

it('clamps scores above 1.0 and below 0.0', function () {
    Http::fake([OUTCOME_URL => Http::response(successResponseXml())]);

    app(BasicOutcomesClient::class)->replaceResult(lti11LaunchForOutcomes(), 150.0, 100.0);
    Http::assertSent(fn ($r) => str_contains($r->body(), '<textString>1.0000</textString>'));

    Http::fake([OUTCOME_URL => Http::response(successResponseXml())]);
    app(BasicOutcomesClient::class)->replaceResult(lti11LaunchForOutcomes(), -10.0, 100.0);
    Http::assertSent(fn ($r) => str_contains($r->body(), '<textString>0.0000</textString>'));
});

it('throws LtiFeatureNotSupportedException when launch is missing outcome URL', function () {
    $launch = lti11LaunchForOutcomes(['lis_outcome_service_url' => null, 'lis_result_sourcedid' => null]);

    expect(fn () => app(BasicOutcomesClient::class)->replaceResult($launch, 1.0, 1.0))
        ->toThrow(LtiFeatureNotSupportedException::class);
});

it('throws LtiException on HTTP failure', function () {
    Http::fake([OUTCOME_URL => Http::response('server error', 500)]);

    expect(fn () => app(BasicOutcomesClient::class)->replaceResult(lti11LaunchForOutcomes(), 1.0, 1.0))
        ->toThrow(LtiException::class);
});

it('throws LtiException when the LMS returns a failure status in the XML body', function () {
    $failureXml = str_replace('success', 'failure', successResponseXml());
    Http::fake([OUTCOME_URL => Http::response($failureXml)]);

    expect(fn () => app(BasicOutcomesClient::class)->replaceResult(lti11LaunchForOutcomes(), 1.0, 1.0))
        ->toThrow(LtiException::class, 'failure');
});

it('throws when scoreMaximum is zero', function () {
    expect(fn () => app(BasicOutcomesClient::class)->replaceResult(lti11LaunchForOutcomes(), 1.0, 0.0))
        ->toThrow(LtiException::class, 'scoreMaximum must be greater than zero');
});

it('reads a score back via readResult', function () {
    $readResponse = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <imsx_POXEnvelopeResponse xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
          <imsx_POXHeader>
            <imsx_POXResponseHeaderInfo>
              <imsx_statusInfo>
                <imsx_codeMajor>success</imsx_codeMajor>
              </imsx_statusInfo>
            </imsx_POXResponseHeaderInfo>
          </imsx_POXHeader>
          <imsx_POXBody>
            <readResultResponse>
              <result><resultScore><language>en</language><textString>0.92</textString></resultScore></result>
            </readResultResponse>
          </imsx_POXBody>
        </imsx_POXEnvelopeResponse>
        XML;

    Http::fake([OUTCOME_URL => Http::response($readResponse)]);

    expect(app(BasicOutcomesClient::class)->readResult(lti11LaunchForOutcomes()))->toBe(0.92);
});

it('sends a deleteResult envelope', function () {
    Http::fake([OUTCOME_URL => Http::response(successResponseXml())]);

    app(BasicOutcomesClient::class)->deleteResult(lti11LaunchForOutcomes());

    Http::assertSent(fn ($r) => str_contains($r->body(), '<deleteResultRequest>'));
});
