<?php

namespace RefBytes\Lti\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LtiLaunch extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'lti_launches';

    protected $fillable = [
        'platform_id',
        'tenant_id',
        'message_type',
        'lti_version',
        'resource_link_id',
        'target_link_uri',
        'user_id',
        'roles',
        'claims',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'claims' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LtiPlatform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(LtiPlatform::class, 'platform_id');
    }
}
