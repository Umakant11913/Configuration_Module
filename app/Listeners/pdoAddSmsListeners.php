<?php

namespace App\Listeners;

use App\Events\PdoAddCreditsEvents;
use App\Events\PdoAddSmsEvents;
use App\Mail\SendNotificationPdoAddCredits;
use App\Mail\SendNotificationPdoAddSms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class pdoAddSmsListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $add_sms;
    public function __construct()
    {
        //
    }


    public function handle(PdoAddSmsEvents $event)
    {
        $user = $event->user;
        $add_sms = $event->add_sms;
        $user->notify(new SendNotificationPdoAddSms($user,$add_sms));
    }
}
