<?php

namespace App\Listeners;

use App\Events\PdoPayoutEvent;
use App\Events\SendPayoutEmailAlertEvent;
use App\Mail\SendNotificationPdoPayout;
use App\Mail\SendPayoutEmailAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPayoutEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $payout;
    protected $frequency;

    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PdoPayoutEvent  $event
     * @return void
     */
    public function handle(SendPayoutEmailAlertEvent $event)
    {
        $user = $event->user;
        $payout = $event->payout;
        $frequency = $event->frequency;
        // Send email
        $user->notify(new SendPayoutEmailAlert($user,$payout,$frequency));
    }
}
