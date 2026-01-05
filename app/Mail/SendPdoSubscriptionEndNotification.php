<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendPdoSubscriptionEndNotification extends Notification
{
    use Queueable;

    private $user, $percentage,$expiryDate;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $router, $used_credits, $expiry_date, $notificationSettings, $grace_period)
    {
        $this->user = $user;
        $this->router = $router;
        $this->used_credits = $used_credits;
        $this->expiry_date = $expiry_date;
        $this->notificationSettings = $notificationSettings;
        $this->grace_period = $grace_period;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
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
    public function toMail($notifiable)
    {
        $message = (new MailMessage);

        if($this->grace_period === true) {
            $message->subject('Alert! AP is deactivated.')
                ->greeting('Hello! '.new HtmlString( $this->user->first_name))
                ->line(new HtmlString('AP Router Name: ' .$this->router->name))
                ->line(new HtmlString('Your AP has been deactivated as grace peiod has been ended, your total grace credits are:- ' .$this->used_credits. ' credits and these will be deducted on your next add-on credits.'))
                ->line(new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login'));
        } else {
            $message->subject('Alert! AP is under Grace Period.')
                ->greeting('Hello! '.new HtmlString( $this->user->first_name))
                ->line(new HtmlString('AP Router Name: ' .$this->router->name))
                ->line(new HtmlString('Your AP is under grace period, and expiry date for grace period is '.$this->expiry_date.'. Your total grace credits are:- ' .$this->used_credits. ' credits and these will be deducted on your next add-on credits.'))
                ->line(new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login'));

        }

        return $message;

        /*return (new MailMessage)

            ->subject('Alert! AP is deactivated.')
            ->greeting('Hello! '.new HtmlString( $this->user->first_name))
            ->line(new HtmlString('AP Router Name: ' .$this->router->name))
            ->line(new HtmlString('Your AP has been deactivated as grace peiod has been ended, your total grace credits are:- ' .$this->used_credits. ' credits and these will be deducted on your next add-on credits.'))
            ->line(new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login'));
        */
        /*$message = (new MailMessage)
            ->subject('Alert !')
            ->greeting('Hello! ' . new HtmlString($this->user->first_name . '  ' . $this->user->last_name));

        if ($this->percentage && $this->expiryDate) {
            $message->line('You have used ' . $this->percentage . '% of your credits. Please add more credits')
                ->line('Your subscription will expire in a month. Please renew to continue enjoying our services.')
                ->line('Thank you for using our application!');
        } else if ($this->percentage) {
            $message->line('You have used ' . $this->percentage . '% of your credits. Please add more credits to continue our services')
                ->line('Thank you for using our application!');
        } else if ($this->expiryDate) {
            $message
                ->line('Your subscription will expire in a month. Please renew to continue enjoying our services.')
                ->line('Thank you for using our application!');
        }

        return $message;*/
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {

        if($this->grace_period === true) {
            return [
                'title'=>'Alert! AP Router is deactivated.',
                'message'=>'Hello! '
                .new HtmlString($this->user->first_name)
                .new HtmlString('AP Router Name: ' .$this->router->name)
                .new HtmlString('Your AP has been deactivated as grace peiod has been ended, your total grace credits are:- ' .$this->used_credits. ' credits and these will be deducted on your next add-on credits.')
                .new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login')
            ];
        } else {
            return [
                'title'=>'Alert! AP is under Grace Period.',
                'message'=>'Hello! '
                    .new HtmlString($this->user->first_name)
                    .new HtmlString('AP Router Name: ' .$this->router->name)
                    .new HtmlString('Your AP is under grace period, and expiry date for grace period is '.$this->expiry_date.'. Your total grace credits are:- ' .$this->used_credits. ' credits and these will be deducted on your next add-on credits.')
                    .new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login')
            ];
        }



        /*if ($this->percentage && $this->expiryDate) {
            return [
                'title'=>'Alert!',
                'message'=>'Hello! '.new HtmlString($this->user->first_name).'You have used ' . $this->percentage . '% of your credits. Please add more credits'
            ];
        } else if ($this->percentage) {
            return [
                'title'=>'Alert!',
                'message'=>'Hello! '.new HtmlString($this->user->first_name).'You have used ' . $this->percentage . '% of your credits. Please add more credits to continue our services'
            ];
        } else if ($this->expiryDate) {
            return [
                'title'=>'Alert!',
                'message'=>'Hello! '.new HtmlString($this->user->first_name).'Your subscription will expire in a month. Please renew to continue enjoying our services.'
            ];
        }*/
    }
}
