<?php

namespace RefBytes\Lti\DataTransferObjects\ContentItems;

use RefBytes\Lti\Contracts\ContentItem;

class LtiResourceLinkItem implements ContentItem
{
    private ?string $title = null;

    private ?string $text = null;

    /** @var array{url: string, width: int, height: int}|null */
    private ?array $icon = null;

    /** @var array{url: string, width: int, height: int}|null */
    private ?array $thumbnail = null;

    /** @var array<string, mixed>|null */
    private ?array $custom = null;

    /** @var array{scoreMaximum: float, label?: string}|null */
    private ?array $lineItem = null;

    /** @var array{startDateTime?: string}|null */
    private ?array $available = null;

    /** @var array{endDateTime?: string}|null */
    private ?array $submission = null;

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
     * @param  array<string, mixed>  $custom
     */
    public function custom(array $custom): self
    {
        $this->custom = $custom;

        return $this;
    }

    public function lineItem(float $scoreMaximum, ?string $label = null): self
    {
        $this->lineItem = array_filter([
            'scoreMaximum' => $scoreMaximum,
            'label' => $label,
        ], fn ($v) => $v !== null);

        return $this;
    }

    public function available(?string $startDateTime): self
    {
        $this->available = array_filter(['startDateTime' => $startDateTime], fn ($v) => $v !== null);

        return $this;
    }

    public function submission(?string $endDateTime): self
    {
        $this->submission = array_filter(['endDateTime' => $endDateTime], fn ($v) => $v !== null);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => 'ltiResourceLink',
            'url' => $this->url,
            'title' => $this->title,
            'text' => $this->text,
            'icon' => $this->icon,
            'thumbnail' => $this->thumbnail,
            'custom' => $this->custom,
            'lineItem' => $this->lineItem,
            'available' => $this->available,
            'submission' => $this->submission,
        ], fn ($v) => $v !== null);
    }
}
