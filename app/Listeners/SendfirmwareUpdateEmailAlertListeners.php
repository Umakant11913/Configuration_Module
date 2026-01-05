<?php

namespace App\Listeners;

use App\Events\SendfirmwareUpdateEmailAlertEvents;
use App\Mail\SendfirmwareUpdateEmailAlert;
use Illuminate\Queue\InteractsWithQueue;

class SendfirmwareUpdateEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $router;
    protected $frequency;
    public $model;

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
    public function handle(SendfirmwareUpdateEmailAlertEvents $event)
    {
        $user = $event->user;
        $router = $event->router;
        $frequency = $event->frequency;
        $model = $event->model;

        // Send email
        $user->notify(new SendfirmwareUpdateEmailAlert($user,$router, $frequency,$model ));
    }
}
