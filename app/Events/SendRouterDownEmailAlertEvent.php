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

class SendRouterDownEmailAlertEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $router_down;
    public $frequency;

    public function __construct(User $user, $router_down, $frequency)
    {
        $this->user = $user;
        $this->router_down = $router_down;
        $this->frequency = $frequency;

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
