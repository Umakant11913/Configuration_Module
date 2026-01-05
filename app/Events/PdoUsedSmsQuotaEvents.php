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

class PdoUsedSmsQuotaEvents
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $percentageUsed;
    public $pdoSmsQuota;

    public function __construct(User $user,  $percentageUsed,$pdoSmsQuota )
    {
        $this->user = $user;
        $this->percentageUsed = $percentageUsed;
        $this->pdoSmsQuota = $pdoSmsQuota;
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
