<?php

namespace App\Listeners;

use App\Events\SendApWiseReportEmailAlertEvents;
use App\Mail\SendApWiseReportEmailAlerts;
use Illuminate\Queue\InteractsWithQueue;

class SendApWiseReportEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $frequency;
    public $apWiseReport;

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
    public function handle(SendApWiseReportEmailAlertEvents  $event)
    {
        $user = $event->user;
        $apWiseReport = $event->apWiseReport;
        $frequency = $event->frequency;

        // Send email
        $user->notify(new SendApWiseReportEmailAlerts($user,$apWiseReport, $frequency));
    }
}
