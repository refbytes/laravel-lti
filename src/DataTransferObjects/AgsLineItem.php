<?php

namespace RefBytes\Lti\DataTransferObjects;

class AgsLineItem
{
    public function __construct(
        public readonly float $scoreMaximum,
        public readonly string $label,
        public readonly ?string $id = null,
        public ?string $resourceId = null,
        public ?string $tag = null,
        public ?string $resourceLinkId = null,
        public ?string $startDateTime = null,
        public ?string $endDateTime = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scoreMaximum: (float) $data['scoreMaximum'],
            label: $data['label'],
            id: $data['id'] ?? null,
            resourceId: $data['resourceId'] ?? null,
            tag: $data['tag'] ?? null,
            resourceLinkId: $data['resourceLinkId'] ?? null,
            startDateTime: $data['startDateTime'] ?? null,
            endDateTime: $data['endDateTime'] ?? null,
        );
    }

    public static function make(string $label, float $scoreMaximum): self
    {
        return new self(scoreMaximum: $scoreMaximum, label: $label);
    }

    public function resourceId(string $resourceId): self
    {
        $this->resourceId = $resourceId;

        return $this;
    }

    public function tag(string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function resourceLinkId(string $resourceLinkId): self
    {
        $this->resourceLinkId = $resourceLinkId;

        return $this;
    }

    public function startDateTime(string $startDateTime): self
    {
        $this->startDateTime = $startDateTime;

        return $this;
    }

    public function endDateTime(string $endDateTime): self
    {
        $this->endDateTime = $endDateTime;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'scoreMaximum' => $this->scoreMaximum,
            'label' => $this->label,
            'resourceId' => $this->resourceId,
            'tag' => $this->tag,
            'resourceLinkId' => $this->resourceLinkId,
            'startDateTime' => $this->startDateTime,
            'endDateTime' => $this->endDateTime,
        ], fn ($v) => $v !== null);
    }
}
