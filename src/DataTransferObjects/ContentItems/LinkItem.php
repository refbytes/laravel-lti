<?php

namespace RefBytes\Lti\DataTransferObjects\ContentItems;

use RefBytes\Lti\Contracts\ContentItem;

class LinkItem implements ContentItem
{
    private ?string $title = null;

    private ?string $text = null;

    /** @var array{url: string, width: int, height: int}|null */
    private ?array $icon = null;

    /** @var array{url: string, width: int, height: int}|null */
    private ?array $thumbnail = null;

    public function __construct(
        private string $url,
    ) {}

    public static function make(string $url): self
    {
        return new self($url);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function icon(string $url, int $width, int $height): self
    {
        $this->icon = ['url' => $url, 'width' => $width, 'height' => $height];

        return $this;
    }

    public function thumbnail(string $url, int $width, int $height): self
    {
        $this->thumbnail = ['url' => $url, 'width' => $width, 'height' => $height];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => 'link',
            'url' => $this->url,
            'title' => $this->title,
            'text' => $this->text,
            'icon' => $this->icon,
            'thumbnail' => $this->thumbnail,
        ], fn ($v) => $v !== null);
    }
}
