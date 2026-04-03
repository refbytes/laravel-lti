<?php

use RefBytes\Lti\DataTransferObjects\NrpsMember;

it('creates a member from full array data', function () {
    $member = NrpsMember::fromArray([
        'user_id' => 'user-42',
        'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership/Learner'],
        'status' => 'Active',
        'name' => 'Jane Smith',
        'given_name' => 'Jane',
        'family_name' => 'Smith',
        'email' => 'jane@example.com',
        'lis_person_sourcedid' => 'SIS123',
        'picture' => 'https://example.com/avatar.png',
    ]);

    expect($member->userId)->toBe('user-42')
        ->and($member->roles)->toBe(['http://purl.imsglobal.org/vocab/lis/v2/membership/Learner'])
        ->and($member->status)->toBe('Active')
        ->and($member->name)->toBe('Jane Smith')
        ->and($member->givenName)->toBe('Jane')
        ->and($member->familyName)->toBe('Smith')
        ->and($member->email)->toBe('jane@example.com')
        ->and($member->lisPersonSourcedid)->toBe('SIS123')
        ->and($member->picture)->toBe('https://example.com/avatar.png');
});

it('handles optional fields as null', function () {
    $member = NrpsMember::fromArray([
        'user_id' => 'user-1',
        'roles' => [],
        'status' => 'Active',
    ]);

    expect($member->name)->toBeNull()
        ->and($member->email)->toBeNull()
        ->and($member->picture)->toBeNull();
});

it('checks role membership', function () {
    $member = NrpsMember::fromArray([
        'user_id' => 'user-1',
        'roles' => [
            'http://purl.imsglobal.org/vocab/lis/v2/membership/Instructor',
            'http://purl.imsglobal.org/vocab/lis/v2/membership/Learner',
        ],
        'status' => 'Active',
    ]);

    expect($member->hasRole('http://purl.imsglobal.org/vocab/lis/v2/membership/Instructor'))->toBeTrue()
        ->and($member->hasRole('http://purl.imsglobal.org/vocab/lis/v2/membership/Administrator'))->toBeFalse()
        ->and($member->isInstructor())->toBeTrue()
        ->and($member->isLearner())->toBeTrue();
});
