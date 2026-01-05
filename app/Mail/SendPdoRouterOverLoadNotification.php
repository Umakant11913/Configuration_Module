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

class SendPdoRouterOverLoadNotification extends Notification
{
    use Queueable;

    private $user;
    private $routerFound;
    protected $router;
    protected $notificationSettings;
    protected $locationName;
    public function __construct($user,$routerFound, $router,  $notificationSettings, $locationName)
    {
        $this->user = $user;
        $this->routerFound = $routerFound;
        $this->router = $router;
        $this->locationName = $locationName;
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
            ->subject('Alert! AP Router is Over Load .')
            ->greeting('Hello! '.new HtmlString( $this->user->first_name))
            ->line(new HtmlString('Router Name: ' .$this->routerFound->name))
            ->line(new HtmlString('Location Name: ' .$this->locationName->name))
            ->line(new HtmlString('Cpu Usage: ' .$this->router->cpu))
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
            .new HtmlString('Router Name: ' .$this->routerFound->name)
            .new HtmlString('Location Name: ' .$this->locationName->name)
            .new HtmlString('Cpu Usage: ' .$this->router->cpu)
            .new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings .For more details, please login to your account.')
        ];
    }
}
