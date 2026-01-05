<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendNotificationPdo extends Notification
{
    use Queueable;

    private $user;
    protected $location;
    protected $notification;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $location, $notification)
    {
        $this->user = $user;
        $this->location = $location;
        $this->notification = $notification;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
            if(!empty($this->notification) && $this->notification->status === 1) {
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

            ->subject('Congratulations')
            ->greeting('Hello! '.new HtmlString( $this->user->first_name))
            ->line('WiFi Router location has been successfully assigned to you. For more details please log in to account.')
            ->line('Thank You!')
            ->line(new HtmlString('<b>Location Name :</b>'. $this->location->name.'</br>'));
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
            'message'=>'Hello! '.new HtmlString( $this->user->first_name) .'WiFi Router location has been successfully assigned to you. For more details please log in to account. Thank You! '.new HtmlString('Location Name : '.$this->location->name)
        ];
    }
}

