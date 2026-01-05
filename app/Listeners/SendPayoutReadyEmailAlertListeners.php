<?php

namespace App\Listeners;

use App\Events\SendPayoutReadyEmailAlertEvents;
use App\Mail\SendPayoutReadyEmailAlert;
use Illuminate\Queue\InteractsWithQueue;

class SendPayoutReadyEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $frequency;
    public $payout;

    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PdoRouterOverLoadEvent  $event
     * @return void
     */
    public function handle(SendPayoutReadyEmailAlertEvents $event)
    {
        $user = $event->user;
        $payout = $event->payout;
        $frequency = $event->frequency;

        // Send email
        $user->notify(new SendPayoutReadyEmailAlert($user,$payout, $frequency));
    }
}
