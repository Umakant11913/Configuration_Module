<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SendFirmwareFailureEmailAlert extends Notification
{
    use Queueable;

    protected $user;
    protected $router;
    protected $frequency;
    public $model;


    public function __construct($user,$router, $frequency, $model)
    {
        $this->user = $user;
        $this->router = $router;
        $this->frequency = $frequency;
        $this->model = $model;
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
            ->subject('Alert! Firmware Update Email Alert .')
            ->greeting('Hello! '.new HtmlString( $this->user->first_name))
            ->line(new HtmlString('Router Name: ' ))
            ->line(new HtmlString('Location Name: ' ))
            ->line(new HtmlString('Cpu Usage: ' ))
            ->line(new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login to your account.'));
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
            'title'=>'Alert! AP Router is Over Load .',
            'message'=>'Hello! '
                .new HtmlString($this->user->first_name)
                .new HtmlString('Router Name: ' )
                .new HtmlString('Location Name: ' )
                .new HtmlString('Cpu Usage: ' )
                .new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings .For more details, please login to your account.')
        ];
    }
}
