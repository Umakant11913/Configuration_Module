<?php

namespace App\Listeners;
use App\Events\SendRouterSlowNetworkEmailAlertEvent;
use App\Mail\SendfirmwareExecutionEmailAlert;
use App\Mail\SendRouterSlowNetworkEmailAlert;
use Illuminate\Queue\InteractsWithQueue;
class SendRouterSlowNetworkEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $slow_network;
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
    public function handle(SendRouterSlowNetworkEmailAlertEvent $event)
    {
        $user = $event->user;
        $slow_network = $event->slow_network;
        $frequency = $event->frequency;

        // Send email
        $user->notify(new SendRouterSlowNetworkEmailAlert($user,$slow_network, $frequency));
    }
}
