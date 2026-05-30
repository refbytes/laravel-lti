<?php

namespace RefBytes\Lti\DataTransferObjects;

/**
 * Builds the body of an LTI 1.3 Dynamic Registration request — the JSON payload
 * POSTed to the platform's registration endpoint. Sources values from
 * `config('lti.tool')` and the package's named routes.
 */
final readonly class ToolConfiguration
{
    public const LTI_TOOL_CONFIG_CLAIM = 'https://purl.imsglobal.org/spec/lti-tool-configuration';

    /**
     * @param  array<string, mixed>  $body  The full registration request body
     */
    public function __construct(public array $body) {}

    /**
     * Build a registration request body from package config + route URLs.
     */
    public static function build(
        string $initiateLoginUri,
        string $launchUri,
        string $jwksUri,
    ): self {
        $tool = (array) config('lti.tool', []);

        $body = array_filter([
            'application_type' => 'web',
            'response_types' => ['id_token'],
            'grant_types' => ['implicit', 'client_credentials'],
            'initiate_login_uri' => $initiateLoginUri,
            'redirect_uris' => [$launchUri],
            'client_name' => $tool['name'] ?? null,
            'jwks_uri' => $jwksUri,
            'logo_uri' => $tool['logo_uri'] ?? null,
            'client_uri' => $tool['client_uri'] ?? null,
            'policy_uri' => $tool['policy_uri'] ?? null,
            'tos_uri' => $tool['tos_uri'] ?? null,
            'token_endpoint_auth_method' => 'private_key_jwt',
            'contacts' => $tool['contacts'] ?? [],
            'scope' => implode(' ', $tool['default_scopes'] ?? []),
            self::LTI_TOOL_CONFIG_CLAIM => self::buildToolConfigClaim($tool, $launchUri),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        return new self($body);
    }

    /**
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    private static function buildToolConfigClaim(array $tool, string $launchUri): array
    {
        $claim = array_filter([
            'domain' => $tool['domain'] ?? null,
            'description' => $tool['description'] ?? null,
            'target_link_uri' => $launchUri,
            'claims' => ['iss', 'sub', 'name', 'given_name', 'family_name', 'email'],
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($tool['deep_linking_enabled'])) {
            $claim['messages'] = [
                [
                    'type' => 'LtiDeepLinkingRequest',
                    'target_link_uri' => $launchUri,
                    'label' => $tool['deep_linking_label'] ?? 'Add Activity',
                ],
            ];
        }

        return $claim;
    }
}
