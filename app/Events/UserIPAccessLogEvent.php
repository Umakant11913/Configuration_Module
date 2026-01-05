<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UserIPAccessLogEvent
{
    use Dispatchable, InteractsWithSockets;
    public $requestData;
    public $routerKey;
    public $userIpAddr;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($requestData, $routerKey, $userIpAddr)
    {
        $this->requestData = $requestData;
        $this->routerKey = $routerKey;
        $this->userIpAddr = $userIpAddr;
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
