<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SendfirmwareUpdateEmailAlert extends Notification
{
    use Queueable;

    protected $user;
    protected $router;
    protected $frequency;
    public $model;


    public function __construct($user, $router, $frequency, $model)
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
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->greeting('Dear' . new HtmlString($this->user->first_name) . ',')
            ->line(new HtmlString('We are pleased to inform you that a new firmware update is now available for your devices. This update includes important security enhancements and performance improvements.'))
            ->line(new HtmlString('We recommend scheduling the update at your earliest convenience to ensure your system is up-to-date. Please let us know if you would like assistance in applying this update, or if you need further details on the changes included.'));
//            ->line(new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login to your account.'));
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
            'message' => 'Dear' . new HtmlString($this->user->first_name) . ','
                . new HtmlString('We are pleased to inform you that a new firmware update is now available for your devices. This update includes important security enhancements and performance improvements.')
                . new HtmlString('We recommend scheduling the update at your earliest convenience to ensure your system is up-to-date. Please let us know if you would like assistance in applying this update, or if you need further details on the changes included.')
//                .new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings .For more details, please login to your account.')
        ];
    }
}
