<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendRouterUpEmailAlert extends Notification
{
    use Queueable;

    private $user;
    protected $router_up;
    protected $frequency;

    public function __construct($user, $router_up, $frequency)
    {
        $this->user = $user;
        $this->router_up = $router_up;
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
        $details = '';

        foreach ($this->router_up as $router) {
            $statusDetails = $router['status'] ?? 'Unknown Status';
            $routerName = $router['router'] ?? 'Unknown Router';
            $location = $router['location'] ?? 'N/A';

            $details .= "<br><strong>Router:</strong> {$routerName}<br>";
            $details .= "<strong>Status:</strong> {$statusDetails}<br>";
            $details .= "<strong>Location:</strong> {$location}<br><br>";
        }

        return (new MailMessage)
            ->greeting('Dear ' . new HtmlString($this->user->first_name) . ',')
            ->line(new HtmlString('We are writing to inform you about the current status of your Access Points (APs):'))
            ->line(new HtmlString($details))
            ->line(new HtmlString('If the Access Point is Up. You can check the real-time status and performance details in your account dashboard.'))
            ->line(new HtmlString('Thank you for your patience, and please let us know if you need further assistance.'));
    }


    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        $details = '';

        foreach ($this->router_up as $routerInfo) {
            $statusDetails = $routerInfo['status'] ?? 'Unknown Status';
            $routerName = $routerInfo['router'] ?? 'Unknown Router';
            $locationName = $routerInfo['location'] ?? 'N/A';

            $details .= '<br><strong>Router:</strong> ' . $routerName . '<br>';
            $details .= '<strong>Status:</strong> ' . $statusDetails . '<br>';
            $details .= '<strong>Location:</strong> ' . $locationName . '<br><br>';
        }

        $message = 'Dear ' . $this->user->first_name . ',<br>'
            . 'We are writing to inform you about the current status of your Access Points (APs):<br>'
            . $details
            . 'If the Access Point is up, our team is actively monitoring and working to resolve the issue as quickly as possible. You can check the real-time status and performance details in your account dashboard.<br>'
            . 'Thank you for your patience, and please let us know if you need further assistance.';

        return [
            'message' => $message,
        ];
    }



}
