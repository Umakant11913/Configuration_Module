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

class PdoSendAdsSummaryEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $userImpression;
    public $ads;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user, $userImpression, $ads)
    {
        $this->user = $user;
        $this->userImpression = $userImpression;
        $this->ads = $ads;
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
