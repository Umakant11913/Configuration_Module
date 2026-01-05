<?php

namespace App\Listeners;

use App\Events\PdoRouterDownEvent;
use App\Mail\SendPdoRouterDownNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPdoRouterDownListeners
{
    use InteractsWithQueue;
    protected $notificationSettings;
    protected $user;
    protected $locationName;
    protected  $routerDown;

    public function __construct(User $user)
    {

    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PdoRouterDownEvent  $event
     * @return void
     */
    public function handle(PdoRouterDownEvent $event)
    {
        $user = $event->user;
        $routerDown = $event->routerDown;
        $notificationSettings = $event->notificationSettings;
        $locationName = $event->locationName;

        // Send email
        $user->notify(new SendPdoRouterDownNotification($user, $routerDown, $notificationSettings, $locationName));
    }
}
