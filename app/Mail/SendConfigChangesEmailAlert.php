<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SendConfigChangesEmailAlert extends Notification
{
    use Queueable;

    protected $user;
    protected $router;
    public $frequency;
    public $configuration_changes;


    public function __construct($user,$router, $frequency, $configuration_changes)
    {
        $this->user = $user;
        $this->router = $router;
        $this->frequency = $frequency;
        $this->configuration_changes = $configuration_changes;
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
            ->subject('Notification: Configuration Change Executed')
            ->greeting('Dear '.new HtmlString( $this->user->first_name).',')
            ->line(new HtmlString('A configuration change has been successfully applied to your network. If this change was not initiated by you, please contact our support team immediately.' ))
            ->line(new HtmlString( 'Thank you for your attention.' ));
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
            'title'=>'Notification: Configuration Change Executed',
            'message'=>'Dear'
                .new HtmlString($this->user->first_name).','
                .new HtmlString('A configuration change has been successfully applied to your network. If this change was not initiated by you, please contact our support team immediately.' )
                .new HtmlString('Thank you for your attention.' )
//                .new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings .For more details, please login to your account.')
        ];
    }
}
