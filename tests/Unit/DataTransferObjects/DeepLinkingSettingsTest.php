<?php

use RefBytes\Lti\DataTransferObjects\DeepLinkingSettings;
use RefBytes\Lti\Exceptions\LtiException;

it('extracts settings from claims', function () {
    $claims = [
        'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings' => [
            'deep_link_return_url' => 'https://platform.example.com/deep_link_return',
            'accept_types' => ['ltiResourceLink', 'link'],
            'accept_presentation_document_targets' => ['iframe', 'window'],
            'accept_multiple' => true,
            'auto_create' => false,
            'data' => 'opaque-data-value',
            'title' => 'Default Title',
            'text' => 'Default Text',
        ],
    ];

    $settings = DeepLinkingSettings::fromClaims($claims);

    expect($settings->deepLinkReturnUrl)->toBe('https://platform.example.com/deep_link_return')
        ->and($settings->acceptTypes)->toBe(['ltiResourceLink', 'link'])
        ->and($settings->acceptPresentationDocumentTargets)->toBe(['iframe', 'window'])
        ->and($settings->acceptMultiple)->toBeTrue()
        ->and($settings->autoCreate)->toBeFalse()
        ->and($settings->data)->toBe('opaque-data-value')
        ->and($settings->title)->toBe('Default Title')
        ->and($settings->text)->toBe('Default Text');
});

it('applies defaults for optional fields', function () {
    $claims = [
        'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings' => [
            'deep_link_return_url' => 'https://platform.example.com/return',
            'accept_types' => ['ltiResourceLink'],
        ],
    ];

    $settings = DeepLinkingSettings::fromClaims($claims);

    expect($settings->acceptPresentationDocumentTargets)->toBe([])
        ->and($settings->acceptMultiple)->toBeFalse()
        ->and($settings->autoCreate)->toBeFalse()
        ->and($settings->data)->toBeNull()
        ->and($settings->title)->toBeNull()
        ->and($settings->text)->toBeNull();
});

it('throws when deep_linking_settings claim is missing', function () {
    DeepLinkingSettings::fromClaims([]);
})->throws(LtiException::class, 'Missing deep_linking_settings');

it('throws when deep_link_return_url is missing', function () {
    DeepLinkingSettings::fromClaims([
        'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings' => [
            'accept_types' => ['ltiResourceLink'],
        ],
    ]);
})->throws(LtiException::class, 'Missing deep_link_return_url');

it('checks if a type is accepted', function () {
    $settings = DeepLinkingSettings::fromClaims([
        'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings' => [
            'deep_link_return_url' => 'https://platform.example.com/return',
            'accept_types' => ['ltiResourceLink', 'link'],
        ],
    ]);

    expect($settings->acceptsType('ltiResourceLink'))->toBeTrue()
        ->and($settings->acceptsType('html'))->toBeFalse();
});
