<?php

namespace App\Events;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class SendPayoutReadyEmailAlertEvents
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $payout;
    public $frequency;
    public function __construct(User $user,$payout, $frequency)
    {
        $this->user = $user;
        $this->payout = $payout;
        $this->frequency = $frequency;
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
