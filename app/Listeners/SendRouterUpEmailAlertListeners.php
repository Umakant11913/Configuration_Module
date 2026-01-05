<?php

namespace App\Listeners;
use App\Events\SendRouterUpEmailAlertEvent;
use App\Mail\SendRouterUpEmailAlert;
use Illuminate\Queue\InteractsWithQueue;

class SendRouterUpEmailAlertListeners
{
    use InteractsWithQueue;

    public $user;
    private $router_up;
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
    public function handle(SendRouterUpEmailAlertEvent $event)
    {
        $user = $event->user;
        $router_up = $event->router_up;
        $frequency = $event->frequency;


        // Send email
        $user->notify(new SendRouterUpEmailAlert($user,$router_up,$frequency));
    }
}
