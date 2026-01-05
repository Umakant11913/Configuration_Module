<?php

namespace App\Listeners;

use App\Events\PdoRouterUpEvent;
use App\Mail\SendPdoRouterUpNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPdoRouterUpListeners
{
    use InteractsWithQueue;
    protected $notificationSettings;
    protected $user;
    protected $locationName;
    protected  $routersDown;

    public function __construct()
    {

    }


    public function handle(PdoRouterUpEvent $event)
    {
        $user = $event->user;
        $routerUp = $event->routerUp;
        $notificationSettings = $event->notificationSettings;
        $locationName = $event->locationName;

        // Send email
        $user->notify(new SendPdoRouterUpNotification($user, $routerUp, $notificationSettings, $locationName));
    }
}
