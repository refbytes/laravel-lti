<?php

namespace RefBytes\Lti\Services;

use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RefBytes\Lti\Exceptions\LtiException;
use RefBytes\Lti\Models\LtiToolKey;

class ToolKeyService
{
    /**
     * Generate a new RSA keypair and store it in the database.
     */
    public function generateKey(?Model $tenant = null, ?int $bits = null): LtiToolKey
    {
        $bits ??= config('lti.tool.key_bits', 2048);
        $algorithm = config('lti.tool.key_algorithm', 'RS256');

        $keyPair = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($keyPair, $privateKeyPem);
        $details = openssl_pkey_get_details($keyPair);

        $key = LtiToolKey::create([
            'tenant_id' => $tenant?->getKey(),
            'kid' => Str::uuid()->toString(),
            'private_key' => $privateKeyPem,
            'public_key' => $details['key'],
            'algorithm' => $algorithm,
            'is_active' => true,
        ]);

        $this->clearJwksCache($tenant);

        return $key;
    }

    /**
     * Get the most recently created active key for signing.
     */
    public function getSigningKey(?Model $tenant = null): LtiToolKey
    {
        $key = LtiToolKey::query()
            ->active()
            ->forTenant($tenant)
            ->latest()
            ->latest('id')
            ->first();

        if (! $key) {
            throw new LtiException('No active tool key found. Run php artisan lti:generate-key to create one.');
        }

        return $key;
    }

    /**
     * Get all active keys for a tenant.
     *
     * @return Collection<int, LtiToolKey>
     */
    public function getActiveKeys(?Model $tenant = null): Collection
    {
        return LtiToolKey::query()
            ->active()
            ->forTenant($tenant)
            ->latest()
            ->get();
    }

    /**
     * Build a JWKS structure from active keys.
     *
     * @return array{keys: array<int, array<string, string>>}
     */
    public function toJwks(?Model $tenant = null): array
    {
        $ttl = config('lti.tool_jwks_ttl', 3600);
        $cacheKey = $this->jwksCacheKey($tenant);

        return $this->cache()->remember($cacheKey, $ttl, function () use ($tenant) {
            $keys = $this->getActiveKeys($tenant);

            return [
                'keys' => $keys->map(fn (LtiToolKey $key) => $this->keyToJwk($key))->values()->all(),
            ];
        });
    }

    /**
     * Sign a JWT payload using the tool's active signing key.
     */
    public function signJwt(array $payload, ?Model $tenant = null): string
    {
        $key = $this->getSigningKey($tenant);

        return JWT::encode($payload, $key->private_key, $key->algorithm, $key->kid);
    }

    /**
     * Deactivate a key by its kid.
     */
    public function deactivateKey(string $kid): void
    {
        $key = LtiToolKey::where('kid', $kid)->firstOrFail();
        $key->update(['is_active' => false]);

        $this->clearJwksCache(
            $key->tenant_id ? $key->tenant : null
        );
    }

    /**
     * Generate a new key, optionally deactivating all previous keys.
     */
    public function rotateKeys(?Model $tenant = null, bool $deactivatePrevious = false): LtiToolKey
    {
        if ($deactivatePrevious) {
            LtiToolKey::query()
                ->active()
                ->forTenant($tenant)
                ->update(['is_active' => false]);
        }

        return $this->generateKey($tenant);
    }

    /**
     * Convert an LtiToolKey to JWK format.
     *
     * @return array<string, string>
     */
    private function keyToJwk(LtiToolKey $key): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($key->public_key));
        $rsa = $details['rsa'];

        return [
            'kty' => 'RSA',
            'alg' => $key->algorithm,
            'use' => 'sig',
            'kid' => $key->kid,
            'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
        ];
    }

    private function clearJwksCache(?Model $tenant): void
    {
        $this->cache()->forget($this->jwksCacheKey($tenant));
    }

    private function jwksCacheKey(?Model $tenant): string
    {
        $prefix = config('lti.cache_prefix', 'lti:');
        $tenantKey = $tenant ? $tenant->getKey() : 'global';

        return $prefix.'tool_jwks:'.$tenantKey;
    }

    private function cache(): Repository
    {
        return Cache::store(config('lti.cache_store'));
    }
}
