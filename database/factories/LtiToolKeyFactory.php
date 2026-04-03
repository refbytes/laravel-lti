<?php

namespace RefBytes\Lti\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RefBytes\Lti\Models\LtiToolKey;

/**
 * @extends Factory<LtiToolKey>
 */
class LtiToolKeyFactory extends Factory
{
    protected $model = LtiToolKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $keyPair = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($keyPair, $privateKeyPem);
        $details = openssl_pkey_get_details($keyPair);

        return [
            'kid' => Str::uuid()->toString(),
            'private_key' => $privateKeyPem,
            'public_key' => $details['key'],
            'algorithm' => 'RS256',
            'is_active' => true,
            'expires_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
