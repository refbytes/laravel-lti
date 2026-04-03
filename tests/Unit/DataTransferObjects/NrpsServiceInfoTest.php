<?php

use RefBytes\Lti\DataTransferObjects\NrpsServiceInfo;
use RefBytes\Lti\Exceptions\LtiException;

it('extracts service info from claims', function () {
    $claims = [
        'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice' => [
            'context_memberships_url' => 'https://canvas.example.com/api/lti/courses/123/memberships',
            'service_versions' => ['2.0'],
        ],
    ];

    $info = NrpsServiceInfo::fromClaims($claims);

    expect($info->contextMembershipsUrl)->toBe('https://canvas.example.com/api/lti/courses/123/memberships')
        ->and($info->serviceVersions)->toBe(['2.0']);
});

it('throws when nrps claim is missing', function () {
    NrpsServiceInfo::fromClaims([]);
})->throws(LtiException::class, 'Missing NRPS namesroleservice claim');

it('throws when context_memberships_url is missing', function () {
    NrpsServiceInfo::fromClaims([
        'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice' => [
            'service_versions' => ['2.0'],
        ],
    ]);
})->throws(LtiException::class, 'Missing context_memberships_url');

it('checks version support', function () {
    $info = NrpsServiceInfo::fromClaims([
        'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice' => [
            'context_memberships_url' => 'https://example.com/memberships',
            'service_versions' => ['2.0'],
        ],
    ]);

    expect($info->supportsVersion('2.0'))->toBeTrue()
        ->and($info->supportsVersion('1.0'))->toBeFalse();
});
