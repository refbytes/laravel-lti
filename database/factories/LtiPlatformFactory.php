<?php

namespace RefBytes\Lti\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RefBytes\Lti\Models\LtiPlatform;

/**
 * @extends Factory<LtiPlatform>
 */
class LtiPlatformFactory extends Factory
{
    protected $model = LtiPlatform::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = $this->faker->domainName();

        return [
            'issuer' => "https://{$domain}",
            'client_id' => (string) $this->faker->unique()->numberBetween(10000, 99999),
            'deployment_id' => (string) $this->faker->numberBetween(1, 100),
            'auth_url' => "https://{$domain}/api/lti/authorize_redirect",
            'token_url' => "https://{$domain}/login/oauth2/token",
            'jwks_url' => "https://{$domain}/api/lti/security/jwks",
            'name' => $this->faker->company().' LMS',
        ];
    }
}
