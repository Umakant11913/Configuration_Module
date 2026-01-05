<?php

namespace App\Listeners;

use App\Events\SendPlanPurchaseEmailAlertEvent;
use App\Mail\SendPlanPurchaseEmailAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPlanPurchaseEmailAlertListeners
{
    public $user;
    public $wifOrder;
    public $frequency;
    public $internetPlan;
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
     * @param  \App\Events\SendPlanPurchaseEmailAlertEvent  $event
     * @return void
     */
    public function handle(SendPlanPurchaseEmailAlertEvent $event)
    {
         $user = $event->user;
         $wifOrder= $event->wifOrder;
         $frequency = $event->frequency;
         $internetPlan= $event->internetPlan;

         $user->notify(new SendPlanPurchaseEmailAlert($user,$wifOrder,$frequency,$internetPlan));
    }
}
