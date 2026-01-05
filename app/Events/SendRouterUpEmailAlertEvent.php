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

class SendRouterUpEmailAlertEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $router_up;
    public $frequency;
    public function __construct(User $user, $router_up, $frequency)
    {
        $this->user = $user;
        $this->router_up = $router_up;
        $this->frequency = $frequency;

    }
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
