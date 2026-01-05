<?php

namespace App\Listeners;

use App\Events\PdoSmsQuotaEvent;
use App\Mail\SendNotificationPdoSmsQuota;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PdoSmsQuotaListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $percentageUsed;
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(PdoSmsQuotaEvent $event)
    {
        $user = $event->user;
        $total_router = $event->total_router;
        $pdoSettings = $event->pdoSettings;
        $user->notify(new SendNotificationPdoSmsQuota($user, $total_router, $pdoSettings));
    }
}
