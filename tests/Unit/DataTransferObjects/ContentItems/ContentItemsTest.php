<?php

use RefBytes\Lti\DataTransferObjects\ContentItems\FileItem;
use RefBytes\Lti\DataTransferObjects\ContentItems\HtmlItem;
use RefBytes\Lti\DataTransferObjects\ContentItems\ImageItem;
use RefBytes\Lti\DataTransferObjects\ContentItems\LinkItem;
use RefBytes\Lti\DataTransferObjects\ContentItems\LtiResourceLinkItem;

it('builds LtiResourceLinkItem with all fields', function () {
    $item = LtiResourceLinkItem::make('https://tool.example.com/launch')
        ->title('Assignment 1')
        ->text('Complete the quiz')
        ->icon('https://tool.example.com/icon.png', 100, 100)
        ->thumbnail('https://tool.example.com/thumb.png', 200, 200)
        ->custom(['module_id' => '42'])
        ->lineItem(100.0, 'Quiz Score')
        ->available('2026-04-01T00:00:00Z')
        ->submission('2026-04-30T23:59:59Z');

    $array = $item->toArray();

    expect($array['type'])->toBe('ltiResourceLink')
        ->and($array['url'])->toBe('https://tool.example.com/launch')
        ->and($array['title'])->toBe('Assignment 1')
        ->and($array['text'])->toBe('Complete the quiz')
        ->and($array['icon'])->toBe(['url' => 'https://tool.example.com/icon.png', 'width' => 100, 'height' => 100])
        ->and($array['lineItem'])->toBe(['scoreMaximum' => 100.0, 'label' => 'Quiz Score'])
        ->and($array['custom'])->toBe(['module_id' => '42']);
});

it('builds LtiResourceLinkItem with minimal fields', function () {
    $array = LtiResourceLinkItem::make('https://tool.example.com/launch')->toArray();

    expect($array)->toBe([
        'type' => 'ltiResourceLink',
        'url' => 'https://tool.example.com/launch',
    ]);
});

it('builds LinkItem', function () {
    $array = LinkItem::make('https://example.com/resource')
        ->title('External Link')
        ->text('A helpful resource')
        ->toArray();

    expect($array['type'])->toBe('link')
        ->and($array['url'])->toBe('https://example.com/resource')
        ->and($array['title'])->toBe('External Link');
});

it('builds HtmlItem', function () {
    $array = HtmlItem::make('<h1>Hello</h1>')
        ->title('HTML Content')
        ->toArray();

    expect($array['type'])->toBe('html')
        ->and($array['html'])->toBe('<h1>Hello</h1>')
        ->and($array['title'])->toBe('HTML Content');
});

it('builds ImageItem', function () {
    $array = ImageItem::make('https://example.com/image.png')
        ->title('Photo')
        ->width(800)
        ->height(600)
        ->toArray();

    expect($array['type'])->toBe('image')
        ->and($array['url'])->toBe('https://example.com/image.png')
        ->and($array['width'])->toBe(800)
        ->and($array['height'])->toBe(600);
});

it('builds FileItem', function () {
    $array = FileItem::make('https://tool.example.com/file/doc.pdf')
        ->title('Document.pdf')
        ->expiresAt('2026-04-05T12:00:00Z')
        ->toArray();

    expect($array['type'])->toBe('file')
        ->and($array['url'])->toBe('https://tool.example.com/file/doc.pdf')
        ->and($array['title'])->toBe('Document.pdf')
        ->and($array['expiresAt'])->toBe('2026-04-05T12:00:00Z');
});

it('omits null values from toArray', function () {
    $array = LinkItem::make('https://example.com')->toArray();

    expect($array)->toBe([
        'type' => 'link',
        'url' => 'https://example.com',
    ]);
    expect($array)->not->toHaveKey('title')
        ->not->toHaveKey('text')
        ->not->toHaveKey('icon')
        ->not->toHaveKey('thumbnail');
});
