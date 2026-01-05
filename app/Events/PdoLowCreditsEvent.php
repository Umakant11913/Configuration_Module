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

class PdoLowCreditsEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user, $AP, $routerEnable, $routerDisable, $gracePeriod;

    public function __construct(User $user, $AP, $routerEnable, $routerDisable, $gracePeriod)
    {
        $this->user = $user;
        $this->AP = $AP;
        $this->routerEnable = $routerEnable;
        $this->routerDisable = $routerDisable;
        $this->gracePeriod = $gracePeriod;
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
