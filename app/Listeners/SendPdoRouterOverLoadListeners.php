<?php

namespace App\Listeners;

use App\Events\PdoRouterOverLoadEvent;
use App\Mail\SendPdoRouterOverLoadNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPdoRouterOverLoadListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $routerFound;
    protected $router;
    protected $notificationSettings;
    protected $locationName;
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
    public function handle(PdoRouterOverLoadEvent $event)
    {
        $user = $event->user;
        $routerFound = $event->routerFound;
        $notificationSettings = $event->notificationSettings;
        $router = $event->router;
        $locationName = $event->locationName;

        // Send email
        $user->notify(new SendPdoRouterOverLoadNotification($user,$routerFound, $router, $notificationSettings, $locationName));
    }
}
