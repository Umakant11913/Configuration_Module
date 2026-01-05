<?php

namespace App\Console\Commands;

use App\Jobs\UpdatePayableToZohoBook;
use Illuminate\Console\Command;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Facades\Log;

class TestUpdatePayable extends Command
{
    protected $signature = 'test:payable';

    protected $description = 'Command description';

    public function handle()
    {
        UpdatePayableToZohoBook::dispatchSync(0, 4);
        return 1;
    }
}
