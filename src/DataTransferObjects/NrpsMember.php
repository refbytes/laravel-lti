<?php

namespace RefBytes\Lti\DataTransferObjects;

final readonly class NrpsMember
{
    /**
     * @param  array<string>  $roles
     */
    public function __construct(
        public string $userId,
        public array $roles,
        public string $status,
        public ?string $name = null,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $email = null,
        public ?string $lisPersonSourcedid = null,
        public ?string $picture = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            roles: $data['roles'] ?? [],
            status: $data['status'] ?? 'Active',
            name: $data['name'] ?? null,
            givenName: $data['given_name'] ?? null,
            familyName: $data['family_name'] ?? null,
            email: $data['email'] ?? null,
            lisPersonSourcedid: $data['lis_person_sourcedid'] ?? null,
            picture: $data['picture'] ?? null,
        );
    }

    public function hasRole(string $roleUri): bool
    {
        return in_array($roleUri, $this->roles, true);
    }

    public function isInstructor(): bool
    {
        return $this->hasRole('http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor')
            || $this->hasRole('http://purl.imsglobal.org/vocab/lis/v2/membership/Instructor');
    }

    public function isLearner(): bool
    {
        return $this->hasRole('http://purl.imsglobal.org/vocab/lis/v2/membership#Learner')
            || $this->hasRole('http://purl.imsglobal.org/vocab/lis/v2/membership/Learner');
    }
}
