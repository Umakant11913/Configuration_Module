<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendNotificationPdoUsedSmsQuota extends Notification
{
    use Queueable;
    private $user;
    public $percentageUsed;
    public $pdoSmsQuota;

    public function __construct($user ,$percentageUsed, $pdoSmsQuota)
    {
        $this->user = $user;
        $this->percentageUsed = $percentageUsed;
        $this->pdoSmsQuota = $pdoSmsQuota;
    }

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
    public function toMail( object $notifiable) :MailMessage
    {
        return (new MailMessage)

            ->subject('Alert!')
            ->greeting('Hello, ' . new HtmlString($this->user->first_name) . '!')
            ->line('Your SMS usage has exceeded 80% of the quota (' . $this->percentageUsed . '%).')
            ->line('Total SMS Quota used: ' . $this->pdoSmsQuota->sms_used)
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
            'title'=>'Alert!',
            'message'=>'Hello! '.new HtmlString($this->user->first_name) .'Your payout has been approved  successfully. The payout amount is '. $this->percentageUsed .new HtmlString('For more details, please login to your account.')
        ];
    }
}
