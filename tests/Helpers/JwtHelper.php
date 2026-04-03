<?php

namespace RefBytes\Lti\Tests\Helpers;

use Firebase\JWT\JWT;

class JwtHelper
{
    public string $kid;

    private \OpenSSLAsymmetricKey $privateKey;

    private string $publicKeyPem;

    public function __construct()
    {
        $this->kid = 'test-key-1';
        $keyPair = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($keyPair, $privateKeyPem);
        $details = openssl_pkey_get_details($keyPair);

        $this->privateKey = openssl_pkey_get_private($privateKeyPem);
        $this->publicKeyPem = $details['key'];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function encode(array $claims): string
    {
        return JWT::encode($claims, $this->privateKey, 'RS256', $this->kid);
    }

    /**
     * @return array{keys: array<int, array<string, string>>}
     */
    public function jwks(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->publicKeyPem));
        $rsa = $details['rsa'];

        return [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => $this->kid,
                    'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
                    'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
                ],
            ],
        ];
    }
}
