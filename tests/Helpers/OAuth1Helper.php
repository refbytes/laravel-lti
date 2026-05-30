<?php

namespace RefBytes\Lti\Tests\Helpers;

use Illuminate\Support\Str;
use RefBytes\Lti\Services\OAuth1Signer;

/**
 * Test helper that signs LTI 1.1 launch requests the way an LMS would. Use it
 * to produce form params ready to POST to /lti/launch.
 */
class OAuth1Helper
{
    public function __construct(
        private string $consumerKey,
        private string $sharedSecret,
        private OAuth1Signer $signer = new OAuth1Signer,
    ) {}

    /**
     * Sign a set of launch params and return them with `oauth_*` values
     * (including `oauth_signature`) merged in.
     *
     * @param  array<string, string>  $params
     * @return array<string, string>
     */
    public function signLaunchParams(string $launchUrl, array $params, ?int $timestamp = null): array
    {
        $oauth = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_signature_method' => OAuth1Signer::SIGNATURE_METHOD,
            'oauth_timestamp' => (string) ($timestamp ?? time()),
            'oauth_nonce' => Str::random(32),
            'oauth_version' => '1.0',
        ];

        $all = array_merge($oauth, $params);
        $all['oauth_signature'] = $this->signer->sign('POST', $launchUrl, $all, $this->sharedSecret);

        return $all;
    }
}
