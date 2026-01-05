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

class SendNotificationPdoSmsQuota extends Notification
{
    use Queueable;
    private $user;
    private $total_router;
    private $pdoSettings;

    public function __construct($user ,$total_router, $pdoSettings)
    {
        $this->user = $user;
        $this->total_router = $total_router;
        $this->pdoSettings = $pdoSettings;
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

            ->subject('Alert !')
            ->greeting('Hello! '.new HtmlString( $this->user->first_name))
            ->line('You have a total router count of '.$this->total_router . '.')
            ->line('Total SMS Quota for each AP '.$this->pdoSettings->period_quota . ' , Successfully added SMS quota to your total router.')
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
            'message'=>'Hello! '.new HtmlString($this->user->first_name) .'Your payout has been approved  successfully. The payout amount is '. $this->total_router .new HtmlString('For more details, please login to your account.')
        ];
    }
}
