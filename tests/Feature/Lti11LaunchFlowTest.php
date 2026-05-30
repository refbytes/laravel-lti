<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use RefBytes\Lti\Events\LtiLaunchValidated;
use RefBytes\Lti\Models\LtiLaunch;
use RefBytes\Lti\Models\LtiPlatform;
use RefBytes\Lti\Tests\Helpers\OAuth1Helper;

const LTI11_CONSUMER_KEY = 'consumer-1';
const LTI11_SHARED_SECRET = 'secret-shhh';

function lti11Platform(array $overrides = []): LtiPlatform
{
    return LtiPlatform::factory()->create(array_merge([
        'version' => 'LTI-1p0',
        'issuer' => null,
        'auth_url' => null,
        'token_url' => null,
        'jwks_url' => null,
        'client_id' => LTI11_CONSUMER_KEY,
        'shared_secret' => LTI11_SHARED_SECRET,
    ], $overrides));
}

/**
 * @return array<string, string>
 */
function lti11SignedLaunchParams(array $overrides = []): array
{
    $helper = new OAuth1Helper(LTI11_CONSUMER_KEY, LTI11_SHARED_SECRET);

    return $helper->signLaunchParams(route('lti.launch'), array_merge([
        'lti_message_type' => 'basic-lti-launch-request',
        'lti_version' => 'LTI-1p0',
        'user_id' => 'student-99',
        'resource_link_id' => 'rlink-42',
        'roles' => 'Learner',
        'context_id' => 'course-7',
        'context_title' => 'Algebra 101',
        'lis_person_name_full' => 'Jane Doe',
        'lis_person_contact_email_primary' => 'jane@example.com',
    ], $overrides));
}

it('validates an LTI 1.1 launch and dispatches LtiLaunchValidated', function () {
    Event::fake([LtiLaunchValidated::class]);

    lti11Platform();

    $params = lti11SignedLaunchParams();

    $response = $this->post(route('lti.launch'), $params);

    $response->assertOk()
        ->assertJsonFragment([
            'message_type' => 'basic-lti-launch-request',
            'user_id' => 'student-99',
        ]);

    Event::assertDispatched(LtiLaunchValidated::class, function ($event) {
        return $event->launch->ltiVersion === 'LTI-1p0'
            && $event->launch->userId === 'student-99'
            && $event->launch->isLearner()
            && $event->launch->resourceLinkId === 'rlink-42'
            && $event->launch->claim('lis_person_contact_email_primary') === 'jane@example.com';
    });
});

it('detects instructor role from short-form LTI 1.1 roles', function () {
    Event::fake([LtiLaunchValidated::class]);
    lti11Platform();

    $params = lti11SignedLaunchParams(['roles' => 'Instructor,ContentDeveloper']);
    $this->post(route('lti.launch'), $params)->assertOk();

    Event::assertDispatched(LtiLaunchValidated::class, fn ($event) => $event->launch->isInstructor());
});

it('detects instructor role from LIS URN', function () {
    Event::fake([LtiLaunchValidated::class]);
    lti11Platform();

    $params = lti11SignedLaunchParams(['roles' => 'urn:lti:role:ims/lis/Instructor']);
    $this->post(route('lti.launch'), $params)->assertOk();

    Event::assertDispatched(LtiLaunchValidated::class, fn ($event) => $event->launch->isInstructor());
});

it('exposes hasBasicOutcomes() when outcome service URL is present', function () {
    Event::fake([LtiLaunchValidated::class]);
    lti11Platform();

    $params = lti11SignedLaunchParams([
        'lis_outcome_service_url' => 'https://lms.example.com/outcomes',
        'lis_result_sourcedid' => 'sourced-1',
    ]);
    $this->post(route('lti.launch'), $params)->assertOk();

    Event::assertDispatched(LtiLaunchValidated::class, fn ($event) => $event->launch->hasBasicOutcomes());
});

it('persists 1.1 launches when store_launches is enabled', function () {
    config()->set('lti.store_launches', true);
    lti11Platform();

    $this->post(route('lti.launch'), lti11SignedLaunchParams())->assertOk();

    expect(LtiLaunch::count())->toBe(1)
        ->and(LtiLaunch::first()->lti_version)->toBe('LTI-1p0');
});

it('returns 400 when oauth_consumer_key is unknown', function () {
    $helper = new OAuth1Helper('unknown-key', LTI11_SHARED_SECRET);
    $params = $helper->signLaunchParams(route('lti.launch'), [
        'lti_message_type' => 'basic-lti-launch-request',
        'lti_version' => 'LTI-1p0',
        'user_id' => 'u',
        'resource_link_id' => 'r',
    ]);

    $this->post(route('lti.launch'), $params)
        ->assertStatus(400);
});

it('returns 400 when oauth signature is invalid', function () {
    lti11Platform();

    $helper = new OAuth1Helper(LTI11_CONSUMER_KEY, 'WRONG-SECRET');
    $params = $helper->signLaunchParams(route('lti.launch'), [
        'lti_message_type' => 'basic-lti-launch-request',
        'lti_version' => 'LTI-1p0',
        'user_id' => 'u',
        'resource_link_id' => 'r',
    ]);

    $this->post(route('lti.launch'), $params)
        ->assertStatus(400)
        ->assertJsonFragment(['error' => 'OAuth signature verification failed.']);
});

it('returns 400 when oauth_timestamp is stale', function () {
    lti11Platform();

    $helper = new OAuth1Helper(LTI11_CONSUMER_KEY, LTI11_SHARED_SECRET);
    $params = $helper->signLaunchParams(
        route('lti.launch'),
        ['lti_message_type' => 'basic-lti-launch-request', 'lti_version' => 'LTI-1p0', 'user_id' => 'u', 'resource_link_id' => 'r'],
        timestamp: time() - 3600,
    );

    $this->post(route('lti.launch'), $params)
        ->assertStatus(400)
        ->assertJsonFragment(['error' => 'OAuth timestamp is outside the allowed tolerance window.']);
});

it('rejects replayed nonces', function () {
    lti11Platform();

    $params = lti11SignedLaunchParams();

    $this->post(route('lti.launch'), $params)->assertOk();
    $this->post(route('lti.launch'), $params)
        ->assertStatus(400)
        ->assertJsonFragment(['error' => 'OAuth nonce has already been used (replay detected).']);
});

it('rejects unsupported signature methods', function () {
    lti11Platform();

    $params = lti11SignedLaunchParams();
    $params['oauth_signature_method'] = 'RSA-SHA1';
    // Resign to make signature itself valid for the modified params
    Cache::flush();

    $this->post(route('lti.launch'), $params)
        ->assertStatus(400)
        ->assertJsonFragment(['error' => 'Unsupported oauth_signature_method. Only HMAC-SHA1 is supported.']);
});
