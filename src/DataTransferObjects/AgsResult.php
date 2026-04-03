<?php

namespace RefBytes\Lti\DataTransferObjects;

final readonly class AgsResult
{
    public function __construct(
        public string $id,
        public string $scoreOf,
        public string $userId,
        public ?float $resultScore = null,
        public ?float $resultMaximum = null,
        public ?string $comment = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            scoreOf: $data['scoreOf'],
            userId: $data['userId'],
            resultScore: isset($data['resultScore']) ? (float) $data['resultScore'] : null,
            resultMaximum: isset($data['resultMaximum']) ? (float) $data['resultMaximum'] : null,
            comment: $data['comment'] ?? null,
        );
    }
}
