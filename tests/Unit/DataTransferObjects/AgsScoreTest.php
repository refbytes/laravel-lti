<?php

use RefBytes\Lti\DataTransferObjects\AgsScore;

it('creates a score with defaults', function () {
    $score = AgsScore::make('user-42');

    expect($score->userId)->toBe('user-42')
        ->and($score->activityProgress)->toBe(AgsScore::ACTIVITY_COMPLETED)
        ->and($score->gradingProgress)->toBe(AgsScore::GRADING_FULLY_GRADED)
        ->and($score->timestamp)->not->toBeEmpty()
        ->and($score->scoreGiven)->toBeNull()
        ->and($score->comment)->toBeNull();
});

it('builds a score with fluent API', function () {
    $score = AgsScore::make('user-42')
        ->scoreGiven(85.5)
        ->scoreMaximum(100.0)
        ->comment('Well done!')
        ->activityProgress(AgsScore::ACTIVITY_SUBMITTED)
        ->gradingProgress(AgsScore::GRADING_PENDING);

    expect($score->scoreGiven)->toBe(85.5)
        ->and($score->scoreMaximum)->toBe(100.0)
        ->and($score->comment)->toBe('Well done!')
        ->and($score->activityProgress)->toBe('Submitted')
        ->and($score->gradingProgress)->toBe('Pending');
});

it('serializes to array correctly', function () {
    $score = AgsScore::make('user-42')
        ->scoreGiven(90.0)
        ->scoreMaximum(100.0)
        ->timestamp('2026-04-03T12:00:00+00:00');

    $array = $score->toArray();

    expect($array['userId'])->toBe('user-42')
        ->and($array['scoreGiven'])->toBe(90.0)
        ->and($array['scoreMaximum'])->toBe(100.0)
        ->and($array['activityProgress'])->toBe('Completed')
        ->and($array['gradingProgress'])->toBe('FullyGraded')
        ->and($array['timestamp'])->toBe('2026-04-03T12:00:00+00:00');

    expect($array)->not->toHaveKey('comment');
});
