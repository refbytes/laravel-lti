<?php

namespace RefBytes\Lti\DataTransferObjects;

final readonly class NrpsContext
{
    public function __construct(
        public string $id,
        public ?string $label = null,
        public ?string $title = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            label: $data['label'] ?? null,
            title: $data['title'] ?? null,
        );
    }
}
