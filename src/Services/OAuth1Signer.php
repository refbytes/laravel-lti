<?php

namespace RefBytes\Lti\Services;

/**
 * Hand-rolled OAuth 1.0a HMAC-SHA1 signing and verification (RFC 5849).
 *
 * Used in two places:
 *  - Verifying incoming LTI 1.1 launches signed by an LMS
 *  - Signing outgoing Basic Outcomes Service (POX) requests to an LMS
 *
 * LTI 1.1 uses two-legged OAuth (consumer key + secret, no token), so the
 * token secret portion of the signing key is always empty.
 */
class OAuth1Signer
{
    public const SIGNATURE_METHOD = 'HMAC-SHA1';

    /**
     * Compute the HMAC-SHA1 signature for an OAuth 1.0a request.
     *
     * @param  string  $method        HTTP method (uppercased internally)
     * @param  string  $url           Request URL (query string stripped, normalized)
     * @param  array<string, string>  $params  All oauth_* params + form params (NOT including oauth_signature)
     * @param  string  $consumerSecret  Shared secret
     */
    public function sign(string $method, string $url, array $params, string $consumerSecret): string
    {
        $baseString = $this->buildBaseString($method, $url, $params);
        $signingKey = self::percentEncode($consumerSecret).'&';

        return base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    }

    /**
     * Verify a signature using constant-time comparison.
     *
     * @param  array<string, string>  $params  All params including oauth_signature
     */
    public function verify(string $method, string $url, array $params, string $consumerSecret): bool
    {
        $providedSignature = $params['oauth_signature'] ?? '';
        unset($params['oauth_signature']);

        $expected = $this->sign($method, $url, $params, $consumerSecret);

        return hash_equals($expected, $providedSignature);
    }

    /**
     * Compute the SHA-1 body hash used for non-form-encoded request bodies
     * (e.g. XML for Basic Outcomes). Returned as base64 per the OAuth body
     * hash extension spec.
     */
    public function bodyHash(string $body): string
    {
        return base64_encode(sha1($body, true));
    }

    /**
     * Build an `Authorization: OAuth ...` header value from a set of oauth_*
     * params. Useful for signing outgoing requests (Basic Outcomes).
     *
     * @param  array<string, string>  $oauthParams
     */
    public function buildAuthorizationHeader(array $oauthParams): string
    {
        $pairs = [];
        foreach ($oauthParams as $key => $value) {
            $pairs[] = self::percentEncode($key).'="'.self::percentEncode($value).'"';
        }

        return 'OAuth '.implode(', ', $pairs);
    }

    /**
     * Build the signature base string per RFC 5849 §3.4.1:
     *   METHOD & PERCENT(URL) & PERCENT(SORTED_ENCODED_PARAMS)
     *
     * @param  array<string, string>  $params
     */
    public function buildBaseString(string $method, string $url, array $params): string
    {
        $normalizedUrl = self::normalizeUrl($url);
        $normalizedParams = self::normalizeParameters($params);

        return strtoupper($method)
            .'&'.self::percentEncode($normalizedUrl)
            .'&'.self::percentEncode($normalizedParams);
    }

    /**
     * Normalize a URL per RFC 5849 §3.4.1.2: lowercase scheme/host, drop the
     * default port for the scheme, drop the query string and fragment.
     */
    public static function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';

        $port = $parts['port'] ?? null;
        $defaultPort = $scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : null);
        $portStr = ($port && $port !== $defaultPort) ? ':'.$port : '';

        return $scheme.'://'.$host.$portStr.$path;
    }

    /**
     * Normalize parameters per RFC 5849 §3.4.1.3.2: percent-encode keys & values,
     * sort by key then value, join as `k=v&k=v`.
     *
     * @param  array<string, string>  $params
     */
    public static function normalizeParameters(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = [self::percentEncode((string) $key), self::percentEncode((string) $value)];
        }

        usort($pairs, function ($a, $b) {
            return $a[0] === $b[0] ? strcmp($a[1], $b[1]) : strcmp($a[0], $b[0]);
        });

        return implode('&', array_map(fn ($p) => $p[0].'='.$p[1], $pairs));
    }

    /**
     * Percent-encode a value per RFC 5849 §3.6, which uses RFC 3986 unreserved
     * characters only. PHP's `rawurlencode()` matches this exactly.
     */
    public static function percentEncode(string $value): string
    {
        return rawurlencode($value);
    }
}
