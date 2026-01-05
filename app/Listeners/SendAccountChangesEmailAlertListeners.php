<?php

namespace App\Listeners;

use App\Events\SendAccountChangesEmailAlertEvent;
use App\Mail\SendAccountChangesEmailAlert;
use Illuminate\Queue\InteractsWithQueue;

class SendAccountChangesEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
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
    public function handle(SendAccountChangesEmailAlertEvent $event)
    {
        $user = $event->user;
        $frequency = $event->frequency;
        // Send email
        $user->notify(new SendAccountChangesEmailAlert($user,$frequency ));
    }
}
