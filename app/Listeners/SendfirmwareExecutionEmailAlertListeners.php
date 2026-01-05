<?php

namespace App\Listeners;

use App\Events\SendfirmwareExecutionEmailAlertEvents;
use App\Mail\SendfirmwareExecutionEmailAlert;
use Illuminate\Queue\InteractsWithQueue;

class SendfirmwareExecutionEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $frequency;
    public $router;

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
    public function handle(SendfirmwareExecutionEmailAlertEvents $event)
    {
        $user = $event->user;
        $router = $event->router;
        $frequency = $event->frequency;

        // Send email
        $user->notify(new SendfirmwareExecutionEmailAlert($user,$router, $frequency));
    }
}
