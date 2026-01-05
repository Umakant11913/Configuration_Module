<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SendAccountChangesEmailAlert extends Notification
{
    use Queueable;

    protected $user;
    public $frequency;

    public function __construct($user, $frequency)
    {
        $this->user = $user;
        $this->frequency = $frequency;
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
            ->subject(' Notification: Customer Account Changes Made')
            ->greeting('Dear ' . new HtmlString($this->user->first_name) . ',')
//            ->line(new HtmlString('Router Name: ' ))
//            ->line(new HtmlString('Location Name: ' ))
//            ->line(new HtmlString('Cpu Usage: ' ))
            ->line(new HtmlString('Changes have been made to your account as per your request. If you did not initiate these changes, please contact us immediately for further investigation.'));
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
            'title' => ' Notification: Customer Account Changes Made',
            'message' => 'Dear ' . new HtmlString($this->user->first_name) . ','
//                .new HtmlString('Router Name: ' )
//                .new HtmlString('Location Name: ' )
//                .new HtmlString('Cpu Usage: ' )
                . new HtmlString('Changes have been made to your account as per your request. If you did not initiate these changes, please contact us immediately for further investigation.')
        ];
    }
}
