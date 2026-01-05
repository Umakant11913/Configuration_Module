<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendPlanPurchaseEmailAlertEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $wifOrder;
    public $frequency;
    public $internetPlan;

    public function __construct(User $user, $wifOrder, $frequency, $internetPlan )
    {
        $this->user = $user;
        $this->wifOrder = $wifOrder;
        $this->frequency = $frequency;
        $this->internetPlan = $internetPlan;

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
