<?php

namespace RefBytes\Lti\Commands;

use Illuminate\Console\Command;
use RefBytes\Lti\Services\ToolKeyService;

class DeactivateToolKeyCommand extends Command
{
    protected $signature = 'lti:deactivate-key
        {kid : The key ID to deactivate}';

    protected $description = 'Deactivate an LTI tool key';

    public function handle(ToolKeyService $toolKeyService): int
    {
        $kid = $this->argument('kid');

        $toolKeyService->deactivateKey($kid);

        $this->info("Key [{$kid}] has been deactivated.");

        return self::SUCCESS;
    }
}
