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

class PdoRouterUpEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $routerUp;
    public $locationName;
    public $notificationSettings;

    public function __construct(User $user, $routerUp, $notificationSettings, $locationName)
    {
        $this->user = $user;
        $this->routerUp = $routerUp;
        $this->notificationSettings = $notificationSettings;
        $this->locationName = $locationName;

    }
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
