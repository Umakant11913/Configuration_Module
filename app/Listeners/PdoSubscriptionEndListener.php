<?php

namespace App\Listeners;

use App\Events\PdoSubscriptionEndAlertEvent;
use App\Mail\SendLowCreditsNotification;
use App\Mail\SendPdoSubscriptionEndNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PdoSubscriptionEndListener
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
     * @param object $event
     * @return void
     */
    public function handle(PdoSubscriptionEndAlertEvent $event)
    {
        $user = $event->user;
        $router = $event->router;
        $used_credits = $event->used_credits;
        $expiry_date = $event->expiry_date;
        $notificationSettings = $event->notificationSettings;
        $grace_period = $event->grace_period;

        // Send email
        $user->notify(new SendPdoSubscriptionEndNotification($user, $router, $used_credits, $expiry_date, $notificationSettings, $grace_period));
    }
}
