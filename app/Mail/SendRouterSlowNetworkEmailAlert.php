<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendRouterSlowNetworkEmailAlert extends Notification
{
    use Queueable;

    protected $user;
    protected $slow_network;
    protected $frequency;


    public function __construct($user, $slow_network, $frequency)
    {
        $this->user = $user;
        $this->slow_network = $slow_network;
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
        foreach ($this->slow_network as $router) {
            $statusDetails = 'Slow Network';
            $routerName = $router['router'] ?? 'Unknown Router';
            $location = $router['location'] ?? 'N/A';
            $details .= "<strong>Status:</strong> {$statusDetails}<br>";
            $details .= "<strong>Location:</strong> {$location}<br><br>";
        }
        //Log::info($this->slow_network);
       $message = (new MailMessage)
            ->greeting('Dear ' . new HtmlString($this->user->first_name) . ',')
            ->line(new HtmlString('We are writing to inform you about the current status of one of your Access Points (AP):'))
            ->line(new HtmlString($details))
            ->line(new HtmlString('If the Access Point is experiencing an overload, our team is actively monitoring and working to resolve the issue as quickly as possible. You can check the real-time status and performance details in your account dashboard.'))
            ->line(new HtmlString('Thank you for your patience, and please let us know if you need further assistance.'));
//            ->line(new HtmlString('You can disable the Email alerts anytime by going on https://pdo.pmwani.net/notification-settings . For more details, please login to your account.'));

        if ($this->slow_network) {

            $message = (new MailMessage)
                ->greeting('Dear ' . new HtmlString($this->user->first_name) . ',')
                ->line(new HtmlString('We are writing to inform you about the current status of one of your Access Points (AP):'))
                ->line(new HtmlString($details))
                ->line(new HtmlString('If the Access Point is experiencing an overload, our team is actively monitoring and working to resolve the issue as quickly as possible. You can check the real-time status and performance details in your account dashboard.'))
                ->line(new HtmlString('Thank you for your patience, and please let us know if you need further assistance.'));

        } else if ($this->slow_network) {

            $message = (new MailMessage)
                ->greeting('Dear ' . new HtmlString($this->user->first_name) . ',')
                ->line(new HtmlString('We are writing to inform you about the current status of one of your Access Points (AP):'))
                ->line(new HtmlString($details))
                ->line(new HtmlString('If the Access Point is experiencing an overload, our team is actively monitoring and working to resolve the issue as quickly as possible. You can check the real-time status and performance details in your account dashboard.'))
                ->line(new HtmlString('Thank you for your patience, and please let us know if you need further assistance.'));
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
        $details = '';

        foreach ($this->slow_network as $routerInfo) {
            $statusDetails = 'Slow Network';
            $routerName = $routerInfo['router'] ?? 'Unknown Router';
            $locationName = $routerInfo['location'] ?? 'N/A';

            $details .= '<strong>Status:</strong> ' . $statusDetails . '<br>';
            $details .= '<strong>Location:</strong> ' . $locationName . '<br><br>';
        }
        if ($this->slow_network) {

            $message = 'Dear ' . $this->user->first_name . ',<br>'
                . 'We are writing to inform you about the current status of your Access Points (APs):<br>'
                . $details
                . 'Your AP is having slow network issue<br>'
                . 'Thank you for your patience, and please let us know if you need further assistance.';

        } else if ($this->slow_network) {

            $message = 'Dear ' . $this->user->first_name . ',<br>'
                . 'We are writing to inform you about the current status of your Access Points (APs):<br>'
                . $details
                . 'Your AP is having very slow network issue<br>'
                . 'Thank you for your patience, and please let us know if you need further assistance.';
        }

        return [
            'message' => $message,
        ];
    }
}
