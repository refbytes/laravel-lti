<?php

namespace RefBytes\Lti\DataTransferObjects\ContentItems;

use RefBytes\Lti\Contracts\ContentItem;

class FileItem implements ContentItem
{
    private ?string $title = null;

    private ?string $text = null;

    private ?string $expiresAt = null;

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

    public function expiresAt(string $iso8601): self
    {
        $this->expiresAt = $iso8601;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => 'file',
            'url' => $this->url,
            'title' => $this->title,
            'text' => $this->text,
            'expiresAt' => $this->expiresAt,
        ], fn ($v) => $v !== null);
    }
}
