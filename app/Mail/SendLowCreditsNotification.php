<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendLowCreditsNotification extends Notification
{
    use Queueable;

    private $user, $AP, $routerEnable, $routerDisable, $gracePeriod;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $AP, $routerEnable, $routerDisable, $gracePeriod)
    {
        $this->user = $user;
        $this->AP = $AP;
        $this->routerEnable = $routerEnable;
        $this->routerDisable = $routerDisable;
        $this->gracePeriod = $gracePeriod;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
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
    public function toMail($notifiable)
    {
        $message = (new MailMessage)
            ->subject('Alert!')
            ->greeting('Hello! ' . $this->user->first_name . ' ' . $this->user->last_name);

        if ($this->routerEnable == true) {
            $message->line('Your AP: '.$this->AP->name.' Next renewal Date is ' . $this->AP->auto_renewal_date->format('Y-m-d'))
                ->line('Thank you for using our application!');
        } elseif ($this->routerDisable == true && $this->gracePeriod == false) {
            $message->line('Your Current AP: ' . $this->AP->name . ' will be stopped Since You dont have Enough Credits')
                ->line('Thank you for using our application!');
        }elseif ($this->gracePeriod == true && $this->gracePeriod == true) {
            $message->line('Your Current AP: ' . $this->AP->name . ' is under Grace Period. Please Renew Subscription to avoid suspension of APs')
                ->line('Thank you for using our application!');
        }


        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        if ($this->routerEnable == true) {
            return [
                'title' => 'Alert!',
                'message' => 'Your AP next renewal Date is ' . new HtmlString($this->AP->auto_renewal_date->format('Y-m-d')),
            ];
        } elseif ($this->routerDisable == true) {
            return [
                'title' => 'Alert!',
                'message' => 'Your Current AP will be stopped Since You dont have Enough Credits'
            ];
        }
        elseif ($this->gracePeriod == true) {
            return [
                'title' => 'Alert!',
                'message' => 'Your Current AP is under grace period'
            ];
        }
    }
}
