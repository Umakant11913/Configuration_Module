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

class SendNotificationPdoAddSms extends Notification
{ use Queueable;
    private $user;
    private $add_sms;
    public function __construct($user, $add_sms)
    {
        $this->user = $user;
        $this->add_sms = $add_sms;
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

            ->subject('Congratulations!')
            ->greeting('Hello! '.new HtmlString($this->user->first_name))
            ->line('We are pleased to inform you that you have been successfully added on to the SMS add-on. You now have  ' .$this->add_sms->sms_credits .' additional SMS')
            ->line(new HtmlString('For more details, please log in to your account.'));
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
            'message'=>'Hello! '.new HtmlString($this->user->first_name) .'Your payout has been approved  successfully. The payout amount is '.new HtmlString('For more details, please login to your account.')
        ];
    }

}
