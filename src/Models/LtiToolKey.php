<?php

namespace RefBytes\Lti\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LtiToolKey extends Model
{
    use HasFactory;

    protected $table = 'lti_tool_keys';

    protected $fillable = [
        'tenant_id',
        'kid',
        'private_key',
        'public_key',
        'algorithm',
        'is_active',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function tenant(): BelongsTo
    {
        $tenantModel = config('lti.tenant_model');

        return $this->belongsTo($tenantModel ?? Model::class, 'tenant_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeForTenant(Builder $query, ?Model $tenant): void
    {
        if ($tenant) {
            $query->where('tenant_id', $tenant->getKey());
        } else {
            $query->whereNull('tenant_id');
        }
    }
}
