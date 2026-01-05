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

class SendFirmwareSuccessEmailAlertEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $router;
    public $frequency;
    public $model;

    public function __construct(User $user,$router, $frequency,$model )
    {
        $this->user = $user;
        $this->router = $router;
        $this->frequency = $frequency;
        $this->model = $model;

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
