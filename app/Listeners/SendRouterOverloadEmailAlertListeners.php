<?php

namespace App\Listeners;

use App\Events\PdoRouterOverLoadEvent;
use App\Events\SendRouterOverLoadEmailAlertEvent;
use App\Mail\SendPdoRouterOverLoadNotification;
use App\Mail\SendRouterOverLoadEmailAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRouterOverloadEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $router_over_load;
    protected $frequency;

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
    public function handle(SendRouterOverLoadEmailAlertEvent $event)
    {
        $user = $event->user;
        $router_over_load = $event->router_over_load;
        $frequency = $event->frequency;

        // Send email
        $user->notify(new SendRouterOverLoadEmailAlert($user,$router_over_load, $frequency));
    }
}
