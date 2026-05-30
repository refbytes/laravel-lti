<?php

use RefBytes\Lti\Services\OAuth1Signer;

beforeEach(function () {
    $this->signer = new OAuth1Signer;
});

it('produces the correct signature base string format', function () {
    $base = $this->signer->buildBaseString('POST', 'https://example.com/launch', [
        'oauth_consumer_key' => 'key',
        'oauth_nonce' => 'abc',
        'lti_message_type' => 'basic-lti-launch-request',
    ]);

    expect($base)->toStartWith('POST&https%3A%2F%2Fexample.com%2Flaunch&');
});

it('sorts parameters by key then value when normalizing', function () {
    $params = [
        'b' => '2',
        'a' => 'z',
        'a2' => '1',
    ];

    $normalized = OAuth1Signer::normalizeParameters($params);

    expect($normalized)->toBe('a=z&a2=1&b=2');
});

it('normalizes URLs by lowercasing scheme and host, stripping default ports', function () {
    expect(OAuth1Signer::normalizeUrl('HTTPS://Example.COM:443/Path'))->toBe('https://example.com/Path')
        ->and(OAuth1Signer::normalizeUrl('http://example.com:80/launch'))->toBe('http://example.com/launch')
        ->and(OAuth1Signer::normalizeUrl('https://example.com:8443/launch'))->toBe('https://example.com:8443/launch');
});

it('drops query string and fragment from the URL in the base string', function () {
    $base = $this->signer->buildBaseString('POST', 'https://example.com/launch?foo=bar#frag', []);

    expect($base)->toBe('POST&https%3A%2F%2Fexample.com%2Flaunch&');
});

it('sign and verify are symmetric for a representative LTI 1.1 launch', function () {
    $params = [
        'oauth_consumer_key' => 'consumer-key-1',
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => '1700000000',
        'oauth_nonce' => 'unique-nonce-abc',
        'oauth_version' => '1.0',
        'lti_message_type' => 'basic-lti-launch-request',
        'lti_version' => 'LTI-1p0',
        'user_id' => 'student-42',
        'resource_link_id' => 'rlink-1',
        'roles' => 'Learner',
    ];

    $signature = $this->signer->sign('POST', 'https://tool.example.com/lti/launch', $params, 'shared-secret');
    $params['oauth_signature'] = $signature;

    expect($this->signer->verify('POST', 'https://tool.example.com/lti/launch', $params, 'shared-secret'))->toBeTrue();
});

it('verification fails when any parameter is tampered with', function () {
    $params = [
        'oauth_consumer_key' => 'key',
        'oauth_nonce' => 'nonce',
        'oauth_timestamp' => '1700000000',
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_version' => '1.0',
        'user_id' => 'student-1',
    ];

    $params['oauth_signature'] = $this->signer->sign('POST', 'https://example.com/launch', $params, 'secret');
    $params['user_id'] = 'student-evil';

    expect($this->signer->verify('POST', 'https://example.com/launch', $params, 'secret'))->toBeFalse();
});

it('verification fails when shared_secret is wrong', function () {
    $params = ['oauth_consumer_key' => 'k', 'oauth_nonce' => 'n', 'oauth_timestamp' => '1', 'oauth_signature_method' => 'HMAC-SHA1', 'oauth_version' => '1.0'];
    $params['oauth_signature'] = $this->signer->sign('POST', 'https://example.com/launch', $params, 'correct-secret');

    expect($this->signer->verify('POST', 'https://example.com/launch', $params, 'wrong-secret'))->toBeFalse();
});

it('computes a SHA-1 base64 body hash for arbitrary content', function () {
    // sha1('hello') = 'aaf4c61ddcc5e8a2dabede0f3b482cd9aea9434d' hex
    // base64 of that raw binary:
    $expected = base64_encode(sha1('hello', true));

    expect($this->signer->bodyHash('hello'))->toBe($expected);
});

it('builds an Authorization header in OAuth 1.0a format', function () {
    $header = $this->signer->buildAuthorizationHeader([
        'oauth_consumer_key' => 'key with space',
        'oauth_nonce' => 'n',
        'oauth_signature' => 'sig+with/slash',
    ]);

    expect($header)->toStartWith('OAuth ')
        ->and($header)->toContain('oauth_consumer_key="key%20with%20space"')
        ->and($header)->toContain('oauth_signature="sig%2Bwith%2Fslash"');
});

it('uses HMAC-SHA1 as the signature method constant', function () {
    expect(OAuth1Signer::SIGNATURE_METHOD)->toBe('HMAC-SHA1');
});

it('percent-encodes special characters per RFC 3986', function () {
    expect(OAuth1Signer::percentEncode('a b'))->toBe('a%20b')
        ->and(OAuth1Signer::percentEncode('hello/world'))->toBe('hello%2Fworld')
        ->and(OAuth1Signer::percentEncode('a+b=c'))->toBe('a%2Bb%3Dc');
});
