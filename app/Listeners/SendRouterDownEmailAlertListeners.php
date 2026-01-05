<?php

namespace App\Listeners;
use App\Events\SendRouterDownEmailAlertEvent;
use App\Mail\SendRouterDownEmailAlert;
use Illuminate\Queue\InteractsWithQueue;

class SendRouterDownEmailAlertListeners
{
    use InteractsWithQueue;

    public $user;
    private $router_down;
    public $frequency;
    public function __construct()
    {
    }
    /**
     * Handle the event.
     *
     * @param  \App\Events\PdoLocationEvent  $event
     * @return void
     */
    public function handle(SendRouterDownEmailAlertEvent $event)
    {
        $user = $event->user;
        $router_down = $event->router_down;
        $frequency = $event->frequency;


        // Send email
        $user->notify(new SendRouterDownEmailAlert($user,$router_down,$frequency));
    }
}
