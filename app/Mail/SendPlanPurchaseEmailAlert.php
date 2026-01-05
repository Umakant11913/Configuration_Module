<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SendPlanPurchaseEmailAlert extends Notification
{
    use Queueable;

    protected $user;
    protected $wifOrder;
    public $frequency;
    public $internetPlan;


    public function __construct($user,$wifOrder, $frequency, $internetPlan)
    {
        $this->user = $user;
        $this->wifOrder = $wifOrder;
        $this->frequency = $frequency;
        $this->internetPlan = $internetPlan;
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
            ->subject('Confirmation: New Plan Purchase')
            ->greeting('Dear '.new HtmlString( $this->user->first_name).',')
            ->line(new HtmlString('Thank you for purchasing a new plan. Your new plan details have been updated in your account. If you have any questions, please reach out to us' ));
//            ->line(new HtmlString('Location Name: ' ))
//            ->line(new HtmlString('Cpu Usage: ' ))
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
            'title'=>'Confirmation: New Plan Purchase',
            'message' => 'Dear! '
                . new HtmlString($this->user->first_name) . ','
//                .new HtmlString('Router Name: ' )
//                .new HtmlString('Location Name: ' )
                .new HtmlString('Thank you for purchasing a new plan. Your new plan details have been updated in your account. If you have any questions, please reach out to us' )
//                .new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings .For more details, please login to your account.')
        ];
    }
}
