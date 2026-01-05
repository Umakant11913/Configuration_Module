<?php

namespace App\Listeners;

use App\Events\SendfirmwareUpdateEmailAlertEvents;
use App\Mail\SendFirmwareFailureEmailAlert;
use App\Mail\SendfirmwareUpdateEmailAlert;
use Illuminate\Queue\InteractsWithQueue;

class SendFirmwareEmailFailureEmailAlertListeners
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
        $user->notify(new SendFirmwareFailureEmailAlert($user,$router, $frequency,$model ));
    }
}
