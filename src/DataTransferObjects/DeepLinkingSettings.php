<?php

namespace RefBytes\Lti\DataTransferObjects;

use RefBytes\Lti\Exceptions\LtiException;

final readonly class DeepLinkingSettings
{
    private const CLAIM_KEY = 'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings';

    /**
     * @param  array<string>  $acceptTypes
     * @param  array<string>  $acceptPresentationDocumentTargets
     */
    public function __construct(
        public string $deepLinkReturnUrl,
        public array $acceptTypes,
        public array $acceptPresentationDocumentTargets = [],
        public bool $acceptMultiple = false,
        public bool $autoCreate = false,
        public ?string $data = null,
        public ?string $title = null,
        public ?string $text = null,
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims): self
    {
        $settings = $claims[self::CLAIM_KEY] ?? null;

        if (! is_array($settings)) {
            throw new LtiException('Missing deep_linking_settings claim in LTI launch.');
        }

        return new self(
            deepLinkReturnUrl: $settings['deep_link_return_url'] ?? throw new LtiException('Missing deep_link_return_url in deep_linking_settings.'),
            acceptTypes: $settings['accept_types'] ?? [],
            acceptPresentationDocumentTargets: $settings['accept_presentation_document_targets'] ?? [],
            acceptMultiple: (bool) ($settings['accept_multiple'] ?? false),
            autoCreate: (bool) ($settings['auto_create'] ?? false),
            data: $settings['data'] ?? null,
            title: $settings['title'] ?? null,
            text: $settings['text'] ?? null,
        );
    }

    public function acceptsType(string $type): bool
    {
        return in_array($type, $this->acceptTypes, true);
    }
}
