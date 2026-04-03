<?php

namespace RefBytes\Lti\DataTransferObjects\ContentItems;

use RefBytes\Lti\Contracts\ContentItem;

class HtmlItem implements ContentItem
{
    private ?string $title = null;

    private ?string $text = null;

    public function __construct(
        private string $html,
    ) {}

    public static function make(string $html): self
    {
        return new self($html);
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => 'html',
            'html' => $this->html,
            'title' => $this->title,
            'text' => $this->text,
        ], fn ($v) => $v !== null);
    }
}
