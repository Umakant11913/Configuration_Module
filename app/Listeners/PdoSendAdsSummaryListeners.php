<?php

namespace App\Listeners;

use App\Events\PdoSendAdsSummaryEvent;
use App\Mail\SendPdoAdsSummaryNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PdoSendAdsSummaryListeners
{
    use InteractsWithQueue;

    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(PdoSendAdsSummaryEvent $event)
    {
        //Log::info('pdo Listeners');
        $user = $event->user;
        $userImpression = $event->userImpression;
        $ads = $event->ads;
        // Send email
        $user->notify(new SendPdoAdsSummaryNotification($user, $userImpression, $ads));
    }
}
