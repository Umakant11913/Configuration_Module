<?php

namespace App\Listeners;

use App\Events\SendConfigChangesEmailAlertEvent;
use App\Mail\SendConfigChangesEmailAlert;
use Illuminate\Queue\InteractsWithQueue;

class SendConfigChangesEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $router;
    protected $frequency;
    public $configuration_changes;

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
    public function handle(SendConfigChangesEmailAlertEvent $event)
    {
        $user = $event->user;
        $router = $event->router;
        $frequency = $event->frequency;
        $configuration_changes = $event->configuration_changes;

        // Send email
        $user->notify(new SendConfigChangesEmailAlert($user,$router, $frequency, $configuration_changes ));
    }
}
