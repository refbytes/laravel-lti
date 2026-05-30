<?php

namespace RefBytes\Lti\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RefBytes\Lti\Concerns\ResolvesTenant;
use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Exceptions\LtiFeatureNotSupportedException;

/**
 * Client for the LTI 1.1 Basic Outcomes Service (BOS / POX).
 *
 * Posts XML-encoded grade passback requests to the LMS's
 * `lis_outcome_service_url`, signed via OAuth 1.0a with the body hash
 * extension.
 */
class BasicOutcomesClient
{
    use ResolvesTenant;

    private const POX_NAMESPACE = 'http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0';

    public function __construct(
        private OAuth1Signer $signer,
    ) {}

    /**
     * Send a normalized score (scoreGiven / scoreMaximum, clamped to 0..1) for
     * the user identified by the launch's `lis_result_sourcedid`.
     */
    public function replaceResult(
        LtiLaunchData $launch,
        float $scoreGiven,
        float $scoreMaximum,
        ?Model $tenant = null,
    ): void {
        [$outcomeUrl, $sourcedId] = $this->extractOutcomeContext($launch);

        if ($scoreMaximum <= 0) {
            throw new LtiException('scoreMaximum must be greater than zero.');
        }

        $normalized = max(0.0, min(1.0, $scoreGiven / $scoreMaximum));

        $xml = $this->buildReplaceResultXml($sourcedId, $normalized);
        $this->postSignedXml($outcomeUrl, $xml, $launch);
    }

    /**
     * Delete a previously-submitted score for the user.
     */
    public function deleteResult(LtiLaunchData $launch, ?Model $tenant = null): void
    {
        [$outcomeUrl, $sourcedId] = $this->extractOutcomeContext($launch);

        $xml = $this->buildPoxEnvelope('deleteResultRequest', <<<XML
            <resultRecord>
                <sourcedGUID><sourcedId>{$this->escapeXml($sourcedId)}</sourcedId></sourcedGUID>
            </resultRecord>
            XML);

        $this->postSignedXml($outcomeUrl, $xml, $launch);
    }

    /**
     * Read the most recently submitted score for the user. Returns the
     * normalized 0..1 score, or null if no score has been submitted.
     */
    public function readResult(LtiLaunchData $launch, ?Model $tenant = null): ?float
    {
        [$outcomeUrl, $sourcedId] = $this->extractOutcomeContext($launch);

        $xml = $this->buildPoxEnvelope('readResultRequest', <<<XML
            <resultRecord>
                <sourcedGUID><sourcedId>{$this->escapeXml($sourcedId)}</sourcedId></sourcedGUID>
            </resultRecord>
            XML);

        $responseBody = $this->postSignedXml($outcomeUrl, $xml, $launch);

        return $this->extractScoreFromReadResponse($responseBody);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractOutcomeContext(LtiLaunchData $launch): array
    {
        $outcomeUrl = $launch->claim('lis_outcome_service_url');
        $sourcedId = $launch->claim('lis_result_sourcedid');

        if (! $outcomeUrl || ! $sourcedId) {
            throw new LtiFeatureNotSupportedException(
                'Launch is missing lis_outcome_service_url or lis_result_sourcedid; the platform does not support Basic Outcomes for this launch.'
            );
        }

        return [$outcomeUrl, $sourcedId];
    }

    private function buildReplaceResultXml(string $sourcedId, float $normalizedScore): string
    {
        $score = number_format($normalizedScore, 4, '.', '');
        $sourcedIdEscaped = $this->escapeXml($sourcedId);

        return $this->buildPoxEnvelope('replaceResultRequest', <<<XML
            <resultRecord>
                <sourcedGUID><sourcedId>{$sourcedIdEscaped}</sourcedId></sourcedGUID>
                <result>
                    <resultScore>
                        <language>en</language>
                        <textString>{$score}</textString>
                    </resultScore>
                </result>
            </resultRecord>
            XML);
    }

    private function buildPoxEnvelope(string $requestElement, string $bodyContents): string
    {
        $messageId = (string) Str::uuid();
        $ns = self::POX_NAMESPACE;

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <imsx_POXEnvelopeRequest xmlns="{$ns}">
                <imsx_POXHeader>
                    <imsx_POXRequestHeaderInfo>
                        <imsx_version>V1.0</imsx_version>
                        <imsx_messageIdentifier>{$messageId}</imsx_messageIdentifier>
                    </imsx_POXRequestHeaderInfo>
                </imsx_POXHeader>
                <imsx_POXBody>
                    <{$requestElement}>
                        {$bodyContents}
                    </{$requestElement}>
                </imsx_POXBody>
            </imsx_POXEnvelopeRequest>
            XML;
    }

    private function postSignedXml(string $url, string $xml, LtiLaunchData $launch): string
    {
        $oauthParams = [
            'oauth_consumer_key' => $launch->platform->client_id,
            'oauth_signature_method' => OAuth1Signer::SIGNATURE_METHOD,
            'oauth_timestamp' => (string) time(),
            'oauth_nonce' => Str::random(32),
            'oauth_version' => '1.0',
            'oauth_body_hash' => $this->signer->bodyHash($xml),
        ];

        $signature = $this->signer->sign('POST', $url, $oauthParams, (string) $launch->platform->shared_secret);
        $oauthParams['oauth_signature'] = $signature;

        $authHeader = $this->signer->buildAuthorizationHeader($oauthParams);

        try {
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/xml',
            ])->withBody($xml, 'application/xml')->post($url)->throw();
        } catch (RequestException $e) {
            throw new LtiException('Basic Outcomes request failed: '.$e->getMessage(), previous: $e);
        }

        $body = $response->body();
        $this->assertSuccessStatus($body);

        return $body;
    }

    private function assertSuccessStatus(string $responseBody): void
    {
        if (! preg_match('#<imsx_codeMajor>([^<]+)</imsx_codeMajor>#', $responseBody, $matches)) {
            throw new LtiException('Basic Outcomes response is malformed (no imsx_codeMajor).');
        }

        if (strtolower($matches[1]) !== 'success') {
            preg_match('#<imsx_description>([^<]*)</imsx_description>#', $responseBody, $descMatch);
            $description = $descMatch[1] ?? '(no description)';

            throw new LtiException("Basic Outcomes request was not successful: {$matches[1]} — {$description}");
        }
    }

    private function extractScoreFromReadResponse(string $responseBody): ?float
    {
        if (preg_match('#<textString>([^<]+)</textString>#', $responseBody, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
