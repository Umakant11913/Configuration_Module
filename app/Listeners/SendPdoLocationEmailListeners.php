<?php

namespace App\Listeners;

use App\Events\PdoLocationEvent;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\PdoLocationEmail;
use App\Mail\SendNotificationPdo;
use Illuminate\Support\Facades\Mail;

class SendPdoLocationEmailListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $location;
    protected $notification;
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PdoLocationEvent  $event
     * @return void
     */
    public function handle(PdoLocationEvent $event)
    {
        $user = $event->user;
        $location = $event->location;
        $notification = $event->notification;

        // Send email
       $user->notify(new SendNotificationPdo($user, $location, $notification));
    }
}
