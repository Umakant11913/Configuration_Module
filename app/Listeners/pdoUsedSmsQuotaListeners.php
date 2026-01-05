<?php

namespace App\Listeners;

use App\Events\PdoUsedSmsQuotaEvents;
use App\Mail\SendNotificationPdoAddCredits;
use App\Mail\SendNotificationPdoUsedSmsQuota;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class pdoUsedSmsQuotaListeners
{
    use InteractsWithQueue;
    public $user;
    public $percentageUsed;
    public $pdoSmsQuota;

    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(PdoUsedSmsQuotaEvents $event)
    {
        $user = $event->user;
        $percentageUsed = $event->percentageUsed;
        $pdoSmsQuota = $event->pdoSmsQuota;
        $user->notify(new SendNotificationPdoUsedSmsQuota($user,$percentageUsed,$pdoSmsQuota));
    }
}
