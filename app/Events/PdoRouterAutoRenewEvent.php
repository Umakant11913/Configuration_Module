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

class PdoRouterAutoRenewEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $router;
    public $notificationSettings;

    public function __construct(User $user, $router, $notificationSettings)
    {
        $this->user = $user;
        $this->router = $router;
        $this->notificationSettings = $notificationSettings;

    }
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
