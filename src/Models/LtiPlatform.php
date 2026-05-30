<?php

namespace RefBytes\Lti\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LtiPlatform extends Model
{
    use HasFactory;

    protected $table = 'lti_platforms';

    protected $fillable = [
        'tenant_id',
        'issuer',
        'client_id',
        'deployment_id',
        'auth_url',
        'token_url',
        'jwks_url',
        'shared_secret',
        'name',
        'version',
        'registered_at',
        'registration_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'registered_at' => 'datetime',
        'shared_secret' => 'encrypted',
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function tenant(): BelongsTo
    {
        $tenantModel = config('lti.tenant_model');

        return $this->belongsTo($tenantModel ?? Model::class, 'tenant_id');
    }

    /**
     * @return HasMany<LtiLaunch, $this>
     */
    public function launches(): HasMany
    {
        return $this->hasMany(LtiLaunch::class, 'platform_id');
    }

    public function scopeForIssuerAndClientId(Builder $query, string $issuer, string $clientId): void
    {
        $query->where('issuer', $issuer)->where('client_id', $clientId);
    }

    public function scopeForTenant(Builder $query, ?Model $tenant): void
    {
        if ($tenant) {
            $query->where('tenant_id', $tenant->getKey());
        } else {
            $query->whereNull('tenant_id');
        }
    }

    public static function findByIssuerAndClientId(string $issuer, string $clientId, ?Model $tenant = null): ?static
    {
        return static::query()
            ->forIssuerAndClientId($issuer, $clientId)
            ->forTenant($tenant)
            ->first();
    }

    /**
     * Look up an LTI 1.1 platform by its consumer key. The package stores the
     * consumer key in the `client_id` column to unify lookup across versions.
     */
    public static function findByConsumerKey(string $consumerKey, ?Model $tenant = null): ?static
    {
        return static::query()
            ->where('client_id', $consumerKey)
            ->forTenant($tenant)
            ->first();
    }
}
