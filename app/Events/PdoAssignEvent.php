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

class PdoAssignEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

  public $pdoPlan;
  public $user;
  public $credits;
    public function __construct(User $user, $pdoPlan , $credits)
    {
        $this->user = $user;
        $this->pdoPlan = $pdoPlan;
        $this->credits = $credits;
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
