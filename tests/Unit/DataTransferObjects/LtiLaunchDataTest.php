<?php

use RefBytes\Lti\DataTransferObjects\LtiLaunchData;
use RefBytes\Lti\Models\LtiPlatform;

it('provides dot-notation access to claims', function () {
    $data = new LtiLaunchData(
        platform: LtiPlatform::factory()->make(),
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: '1',
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: 'link-1',
        userId: 'user-123',
        roles: [],
        claims: [
            'https://purl.imsglobal.org/spec/lti/claim/context' => [
                'id' => 'course-1',
                'label' => 'CS101',
            ],
        ],
    );

    $context = $data->claim('https://purl.imsglobal.org/spec/lti/claim/context');
    expect($context)->toBeArray()
        ->and($context['id'])->toBe('course-1')
        ->and($context['label'])->toBe('CS101');

    expect($data->claim('nonexistent-claim', 'default'))
        ->toBe('default');
});

it('checks instructor role', function () {
    $data = new LtiLaunchData(
        platform: LtiPlatform::factory()->make(),
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: null,
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: null,
        userId: 'user-1',
        roles: ['http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor'],
        claims: [],
    );

    expect($data->isInstructor())->toBeTrue();
    expect($data->isLearner())->toBeFalse();
});

it('checks learner role', function () {
    $data = new LtiLaunchData(
        platform: LtiPlatform::factory()->make(),
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: null,
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: null,
        userId: 'user-1',
        roles: ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'],
        claims: [],
    );

    expect($data->isLearner())->toBeTrue();
    expect($data->isInstructor())->toBeFalse();
});

it('detects nrps support', function () {
    $data = new LtiLaunchData(
        platform: LtiPlatform::factory()->make(),
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: null,
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: null,
        userId: 'user-1',
        roles: [],
        claims: [
            'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice' => [
                'context_memberships_url' => 'https://canvas.example.com/memberships',
                'service_versions' => ['2.0'],
            ],
        ],
    );

    expect($data->hasNrps())->toBeTrue();
    expect($data->nrpsServiceInfo())->not->toBeNull();
    expect($data->nrpsServiceInfo()->contextMembershipsUrl)->toBe('https://canvas.example.com/memberships');
});

it('returns null nrps info when not present', function () {
    $data = new LtiLaunchData(
        platform: LtiPlatform::factory()->make(),
        messageType: 'LtiResourceLinkRequest',
        ltiVersion: '1.3.0',
        deploymentId: null,
        targetLinkUri: 'https://tool.example.com/launch',
        resourceLinkId: null,
        userId: 'user-1',
        roles: [],
        claims: [],
    );

    expect($data->hasNrps())->toBeFalse();
    expect($data->nrpsServiceInfo())->toBeNull();
});
