<?php

use RefBytes\Lti\DataTransferObjects\AgsServiceInfo;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Services\AgsClient;

it('extracts service info from claims', function () {
    $info = AgsServiceInfo::fromClaims([
        'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint' => [
            'lineitems' => 'https://canvas.example.com/api/lti/courses/123/line_items',
            'lineitem' => 'https://canvas.example.com/api/lti/courses/123/line_items/1',
            'scope' => [
                AgsClient::SCOPE_LINE_ITEM,
                AgsClient::SCOPE_SCORE,
                AgsClient::SCOPE_RESULT_READONLY,
            ],
        ],
    ]);

    expect($info->lineItemsUrl)->toBe('https://canvas.example.com/api/lti/courses/123/line_items')
        ->and($info->lineItemUrl)->toBe('https://canvas.example.com/api/lti/courses/123/line_items/1')
        ->and($info->scopes)->toHaveCount(3);
});

it('throws when ags claim is missing', function () {
    AgsServiceInfo::fromClaims([]);
})->throws(LtiException::class, 'Missing AGS endpoint claim');

it('handles missing optional fields', function () {
    $info = AgsServiceInfo::fromClaims([
        'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint' => [
            'scope' => [AgsClient::SCOPE_SCORE],
        ],
    ]);

    expect($info->lineItemsUrl)->toBeNull()
        ->and($info->lineItemUrl)->toBeNull();
});

it('checks scope capabilities', function () {
    $info = AgsServiceInfo::fromClaims([
        'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint' => [
            'lineitems' => 'https://example.com/line_items',
            'scope' => [
                AgsClient::SCOPE_LINE_ITEM,
                AgsClient::SCOPE_SCORE,
            ],
        ],
    ]);

    expect($info->canManageLineItems())->toBeTrue()
        ->and($info->canReadLineItems())->toBeTrue()
        ->and($info->canPostScores())->toBeTrue()
        ->and($info->canReadResults())->toBeFalse();
});

it('detects readonly line item access', function () {
    $info = AgsServiceInfo::fromClaims([
        'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint' => [
            'lineitems' => 'https://example.com/line_items',
            'scope' => [AgsClient::SCOPE_LINE_ITEM_READONLY],
        ],
    ]);

    expect($info->canManageLineItems())->toBeFalse()
        ->and($info->canReadLineItems())->toBeTrue();
});
