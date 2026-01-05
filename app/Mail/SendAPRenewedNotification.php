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

class SendAPRenewedNotification extends Notification
{
    use Queueable;

    private $user;
    protected $router;
    protected $notificationSettings;

    public function __construct($user, $router,  $notificationSettings)
    {
        $this->user = $user;
        $this->router = $router;
        $this->notificationSettings = $notificationSettings;
    }

    public function via($notifiable)
    {
        if(!empty($this->notificationSettings) && $this->notificationSettings->status === 1) {
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

            ->subject('Alert! AP is being auto-renewed.')
            ->greeting('Hello! '.new HtmlString( $this->user->first_name))
            ->line(new HtmlString('AP Router Name: ' .$this->router->name))
            ->line(new HtmlString('Next Auto-Renewed Date: ' .$this->router->auto_renewal_date->format('d M, Y \a\t h:i A')))
            ->line(new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login'));
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
            'title'=>'Alert! AP Router is auto-renewed.',
            'message'=>'Hello! '
                .new HtmlString($this->user->first_name)
                .new HtmlString('AP Router Name: ' .$this->router->name)
                .new HtmlString('Next Auto-Renewed Date: ' .$this->router->auto_renewal_date->format('d M, Y \a\t h:i A'))
                .new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login')
        ];
    }
}
