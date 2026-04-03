<?php

use RefBytes\Lti\DataTransferObjects\AgsLineItem;

it('creates a line item from array', function () {
    $item = AgsLineItem::fromArray([
        'id' => 'https://platform.example.com/line_items/1',
        'scoreMaximum' => 100,
        'label' => 'Chapter 5 Quiz',
        'resourceId' => 'quiz-231',
        'tag' => 'grade',
        'resourceLinkId' => 'link-42',
        'startDateTime' => '2026-04-01T00:00:00Z',
        'endDateTime' => '2026-04-30T23:59:59Z',
    ]);

    expect($item->id)->toBe('https://platform.example.com/line_items/1')
        ->and($item->scoreMaximum)->toBe(100.0)
        ->and($item->label)->toBe('Chapter 5 Quiz')
        ->and($item->resourceId)->toBe('quiz-231')
        ->and($item->tag)->toBe('grade');
});

it('builds a line item with fluent API', function () {
    $item = AgsLineItem::make('Quiz 1', 50.0)
        ->resourceId('quiz-1')
        ->tag('quiz')
        ->resourceLinkId('link-1')
        ->startDateTime('2026-04-01T00:00:00Z')
        ->endDateTime('2026-04-30T23:59:59Z');

    expect($item->label)->toBe('Quiz 1')
        ->and($item->scoreMaximum)->toBe(50.0)
        ->and($item->resourceId)->toBe('quiz-1')
        ->and($item->tag)->toBe('quiz');
});

it('omits nulls and id from toArray', function () {
    $item = AgsLineItem::make('Assignment', 100.0);

    $array = $item->toArray();

    expect($array)->toBe([
        'scoreMaximum' => 100.0,
        'label' => 'Assignment',
    ]);
    expect($array)->not->toHaveKey('id')
        ->not->toHaveKey('resourceId')
        ->not->toHaveKey('tag');
});

it('includes set fields in toArray', function () {
    $item = AgsLineItem::make('Quiz', 50.0)
        ->tag('quiz')
        ->resourceId('q-1');

    $array = $item->toArray();

    expect($array)->toBe([
        'scoreMaximum' => 50.0,
        'label' => 'Quiz',
        'resourceId' => 'q-1',
        'tag' => 'quiz',
    ]);
});
