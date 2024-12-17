<?php

namespace Martianatwork\FilamentphpAutoResource\Commands;

use Illuminate\Console\Command;

class FilamentphpAutoResourceCommand extends Command
{
    public $signature = 'filamentphp-auto-resource';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
