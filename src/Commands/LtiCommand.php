<?php

namespace RefBytes\Lti\Commands;

use Illuminate\Console\Command;

class LtiCommand extends Command
{
    public $signature = 'laravel-lti';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
