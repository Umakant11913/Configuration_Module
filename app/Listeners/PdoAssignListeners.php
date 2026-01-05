<?php

namespace App\Listeners;

use App\Events\PdoAssignEvent;
use App\Mail\SendNotificationPdoAssign;
use App\Mail\SendNotificationPdoSmsQuota;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PdoAssignListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $pdoPlan;
    protected $credits;
    public function __construct()
    {

    }
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(PdoAssignEvent $event)
    {
        $user = $event->user;
        $pdoPlan = $event->pdoPlan;
        $credits = $event->credits;
        $user->notify(new SendNotificationPdoAssign($user, $pdoPlan, $credits));
    }
}
