<?php

namespace RefBytes\Lti\DataTransferObjects;

final readonly class NrpsMembershipResult
{
    /**
     * @param  array<NrpsMember>  $members
     */
    public function __construct(
        public string $id,
        public ?NrpsContext $context,
        public array $members,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromResponseData(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            context: isset($data['context']) ? NrpsContext::fromArray($data['context']) : null,
            members: array_map(
                fn (array $member) => NrpsMember::fromArray($member),
                $data['members'] ?? [],
            ),
        );
    }
}
