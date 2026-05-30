<?php

namespace RefBytes\Lti\DataTransferObjects;

use RefBytes\Lti\Exceptions\LtiException;

/**
 * Parses an LMS platform's OpenID Configuration document, as fetched from the
 * `openid_configuration` URL provided during LTI 1.3 Dynamic Registration.
 */
final readonly class PlatformConfiguration
{
    public const LTI_PLATFORM_CONFIG_CLAIM = 'https://purl.imsglobal.org/spec/lti-platform-configuration';

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $jwksUri,
        public string $registrationEndpoint,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri', 'registration_endpoint'] as $required) {
            if (empty($data[$required])) {
                throw new LtiException("Platform OpenID configuration is missing required field: {$required}");
            }
        }

        return new self(
            issuer: $data['issuer'],
            authorizationEndpoint: $data['authorization_endpoint'],
            tokenEndpoint: $data['token_endpoint'],
            jwksUri: $data['jwks_uri'],
            registrationEndpoint: $data['registration_endpoint'],
            raw: $data,
        );
    }

    /**
     * Platform's display name from the LTI platform configuration claim, if provided.
     */
    public function productName(): ?string
    {
        return $this->raw[self::LTI_PLATFORM_CONFIG_CLAIM]['product_family_code']
            ?? null;
    }
}
