<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PdoSubscriptionEndAlertEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user, $router, $used_credits, $expiry_date, $notificationSettings, $grace_period;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user, $router, $used_credits, $expiry_date, $notificationSettings, $grace_period)
    {

        $this->user = $user;
        $this->router = $router;
        $this->used_credits = $used_credits;
        $this->expiry_date = $expiry_date;
        $this->notificationSettings = $notificationSettings;
        $this->grace_period = $grace_period;

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
