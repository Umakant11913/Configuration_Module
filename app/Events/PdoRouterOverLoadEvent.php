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

class PdoRouterOverLoadEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $routerFound;
    public $router;
    public $notificationSettings;
    public $locationName;
    public function __construct(User $user,$routerFound, $router, $notificationSettings, $locationName)
    {
        $this->user = $user;
        $this->routerFound = $routerFound;
        $this->router = $router;
        $this->notificationSettings = $notificationSettings;
        $this->locationName = $locationName;
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
