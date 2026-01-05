<?php

namespace App\Listeners;

use App\Events\PdoPayoutEvent;
use App\Mail\SendNotificationPdoPayout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPdoPayoutEmailListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $payout;
    protected $notification;
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PdoPayoutEvent  $event
     * @return void
     */
    public function handle(PdoPayoutEvent $event)
    {
        $user = $event->user;
        $payout = $event->payout;
        $notification = $event->notification;

        // Send email
        $user->notify(new SendNotificationPdoPayout($user,$payout,$notification));
    }
}
