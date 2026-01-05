<?php

namespace App\Listeners;

use App\Events\PdoRouterAutoRenewEvent;
use App\Events\PdoRouterUpEvent;
use App\Mail\SendAPRenewedNotification;
use App\Mail\SendPdoRouterUpNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PdoRouterAutoRenewListener
{
    use InteractsWithQueue;
    protected $user;
    protected  $router;
    protected $notificationSettings;

    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PdoRouterAutoRenewEvent  $event
     * @return void
     */


    public function handle(PdoRouterAutoRenewEvent $event)
    {
        $user = $event->user;
        $router = $event->router;
        $notificationSettings = $event->notificationSettings;

        // Send email
        $user->notify(new SendAPRenewedNotification($user, $router, $notificationSettings));
    }
}
