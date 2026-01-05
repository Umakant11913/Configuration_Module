<?php

namespace App\Listeners;

use App\Events\PdoLowCreditsEvent;
use App\Mail\SendLowCreditsNotification;
use App\Mail\SendNotificationPdoSmsQuota;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class pdoLowCreditsListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(PdoLowCreditsEvent $event)
    {
        $user = $event->user;
        $AP = $event->AP;
        $routerEnable = $event->routerEnable;
        $routerDisable = $event->routerDisable;
        $gracePeriod = $event->gracePeriod;
        $user->notify(new SendLowCreditsNotification($user, $AP,$routerEnable,$routerDisable,$gracePeriod));
    }
}
