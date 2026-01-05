<?php

namespace App\Listeners;

use App\Events\SendLocationEmailAlertEvent;
use App\Mail\SendLocationEmailAlert;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\SendNotificationPdo;
use Illuminate\Support\Facades\Mail;

class SendLocationEmailAlertListeners
{
    use InteractsWithQueue;
    protected $user;
    protected $location;
    protected $frequency;
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\PdoLocationEvent  $event
     * @return void
     */
    public function handle(SendLocationEmailAlertEvent $event)
    {
        $user = $event->user;
        $location = $event->location;
        $frequency = $event->frequency;
        // Send email
        $user->notify(new SendLocationEmailAlert($user, $location, $frequency));
    }
}
