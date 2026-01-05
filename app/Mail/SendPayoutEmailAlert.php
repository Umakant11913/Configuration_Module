<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendPayoutEmailAlert extends Notification
{
    use Queueable;

    private $user;
    protected $payouts;
    protected $frequency;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $payouts, $frequency)
    {
        $this->user = $user;
        $this->payouts = $payouts; // Can be a collection for weekly/monthly or a single payout for daily
        $this->frequency = $frequency; // 'daily', 'weekly', 'monthly', etc.
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $mailMessage = (new MailMessage)
//            ->subject('Payout Notification')
            ->greeting('Dear ' . new HtmlString($this->user->first_name) . '!');

        // If daily, show only one payout
        if ($this->frequency === 'daily') {
            $payout = $this->payouts; // Single payout
            $mailMessage->line('Your payout has been approved. The payout amount is ' . $payout->payout_amount . '.')
                ->line(new HtmlString('For more details, please log in to your account.'));
        } else {
            // For weekly/monthly/custom, show summary of multiple payouts
            $mailMessage->line('Your payout summary for the period:')
                ->line('Below are the payouts for ' . ucfirst($this->frequency) . ' period:');

            $mailMessage->line(new HtmlString('For more details, please log in to your account.'));
        }

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        $message = 'Hello, ' . new HtmlString($this->user->first_name) . '! ';

        // Debugging: Check if $this->payouts is set and its type
        return [
            'title' => 'Payout Notification',
            'message' => $message . ' For more details, please log in to your account.',
            'notification_type' => 'payout',
            'frequency' => $this->frequency
        ];
    }


}


