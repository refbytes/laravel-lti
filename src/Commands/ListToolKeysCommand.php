<?php

namespace RefBytes\Lti\Commands;

use Illuminate\Console\Command;
use RefBytes\Lti\Models\LtiToolKey;

class ListToolKeysCommand extends Command
{
    protected $signature = 'lti:list-keys
        {--tenant= : Filter by tenant ID}';

    protected $description = 'List all LTI tool keys';

    public function handle(): int
    {
        $query = LtiToolKey::query()->latest();

        $tenantId = $this->option('tenant');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $keys = $query->get();

        if ($keys->isEmpty()) {
            $this->info('No keys found.');

            return self::SUCCESS;
        }

        $this->table(
            ['KID', 'Algorithm', 'Active', 'Created', 'Expires'],
            $keys->map(fn (LtiToolKey $key) => [
                $key->kid,
                $key->algorithm,
                $key->is_active ? 'Yes' : 'No',
                $key->created_at->toDateTimeString(),
                $key->expires_at?->toDateTimeString() ?? '-',
            ]),
        );

        return self::SUCCESS;
    }
}
