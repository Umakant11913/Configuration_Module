<?php

namespace App\Listeners;

use App\Events\PdoAddCreditsEvents;
use App\Mail\SendNotificationPdoAddCredits;
use App\Mail\SendNotificationPdoAssign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class pdoAddCreditsListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $credit_history;
    public function __construct()
    {
        //
    }


    public function handle(PdoAddCreditsEvents $event)
    {
        $user = $event->user;
        $credit_history = $event->credit_history;
        $user->notify(new SendNotificationPdoAddCredits($user,$credit_history));
    }
}
