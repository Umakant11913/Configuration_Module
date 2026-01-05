<?php

namespace App\Listeners;

use App\Events\SendUserReportEmailAlertEvents;
use App\Mail\SendUserReportEmailAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;

class SendUserReportEmailAlertListeners  extends Notification
{

    use Queueable;
    protected $user;
    protected $frequency;
    public $allUserReports;

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
    public function handle(SendUserReportEmailAlertEvents $event)
    {
        $user = $event->user;
        $allUserReports = $event->allUserReports;
        $frequency = $event->frequency;

        // Send email
        $user->notify(new SendUserReportEmailAlert($user,$allUserReports, $frequency));
    }
}
