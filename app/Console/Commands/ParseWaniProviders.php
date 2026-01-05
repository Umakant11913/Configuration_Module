<?php

namespace App\Console\Commands;

use App\Jobs\ParseWaniProvidersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ParseWaniProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parse:wani';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ParseWaniProvidersJob::dispatch();
        return 1;
    }
}
