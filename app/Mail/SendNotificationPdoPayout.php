<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendNotificationPdoPayout extends Notification
{
    use Queueable;

    private $user;
    protected $payout;
    protected $notification;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $payout, $notification)
    {
        $this->user = $user;
        $this->payout = $payout;
        $this->notification = $notification;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notification
     * @return array
     */

    public function via($notifiable)
    {
        if(!empty($this->notification) && $this->notification->status === 1) {
            return ['database', 'mail'];
        } else {
            return ['database'];
        }

    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail( object $notifiable) :MailMessage
    {
        return (new MailMessage)

            ->subject('Congratulations')
            ->greeting('Hello! '.new HtmlString( $this->user->first_name))
            ->line('Your payout has been approved  successfully. The payout amount is '. $this->payout->payout_amount . '.')
            ->line(new HtmlString('For more details, please login to your account.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title'=>'Congratulations!',
            'message'=>'Hello! '.new HtmlString($this->user->first_name) .'Your payout has been approved  successfully. The payout amount is '. $this->payout->payout_amount .new HtmlString('For more details, please login to your account.')
        ];
    }
}

