<?php

namespace RefBytes\Lti\Commands;

use Illuminate\Console\Command;
use RefBytes\Lti\Services\ToolKeyService;

class GenerateToolKeyCommand extends Command
{
    protected $signature = 'lti:generate-key
        {--tenant= : Tenant ID to scope the key to}
        {--bits=2048 : RSA key size in bits}
        {--deactivate-previous : Deactivate all previous keys for this tenant}';

    protected $description = 'Generate a new RSA keypair for LTI tool authentication';

    public function handle(ToolKeyService $toolKeyService): int
    {
        $tenantId = $this->option('tenant');
        $bits = (int) $this->option('bits');
        $deactivatePrevious = $this->option('deactivate-previous');

        $tenant = null;
        if ($tenantId && config('lti.tenant_model')) {
            $tenantModel = config('lti.tenant_model');
            $tenant = $tenantModel::findOrFail($tenantId);
        }

        if ($deactivatePrevious) {
            $key = $toolKeyService->rotateKeys($tenant, true);
            $this->info('Previous keys deactivated.');
        } else {
            $key = $toolKeyService->generateKey($tenant, $bits);
        }

        $this->info("Generated new key: {$key->kid}");

        return self::SUCCESS;
    }
}
