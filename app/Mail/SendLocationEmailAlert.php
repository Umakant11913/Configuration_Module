<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendLocationEmailAlert extends Notification
{
    use Queueable;

    private $user;
    protected $location;
    protected $frequency;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $location, $frequency)
    {

        $this->user = $user;
        $this->location = $location;
        $this->frequency = $frequency;
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
    //For Sending Message to User
    public function toMail(object $notifiable): MailMessage
    {
        $locationNames = '';

        foreach ($this->location as $location) {
            $locationNames = ($location->name ?? 'Unknown') . ', ';
        }

        $locationNames = rtrim($locationNames, ', ');

        $message =
            'We wanted to inform you that the following locations have been successfully assigned to your Access Point (AP): ' .
            $locationNames .
            '. You can now view and manage these locations from your account dashboard.';

        return (new MailMessage)
            ->subject('Congratulations')
            ->greeting('Dear ' . new HtmlString($this->user->first_name) . ',')
            ->line(new HtmlString($message))
            ->line(new HtmlString("If you have any questions or need further assistance, please don't hesitate to contact us."))
            ->line(new HtmlString('Thank you for choosing our services.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */

    // For Storing in Database
    public function toArray($notifiable)
    {
        $locationNames = '';

        foreach ($this->location as $location) {
            $locationNames .= ($location->name ?? 'Unknown') . ', ';
        }

        $locationNames = rtrim($locationNames, ', ');

        $message =
            'Dear ' . $this->user->first_name . ',' . PHP_EOL .
            'We wanted to inform you that the following locations have been successfully assigned to your Access Point (AP): ' .
            $locationNames .
            '. You can now view and manage these locations from your account dashboard.' . PHP_EOL .
            'If you have any questions or need further assistance, please don\'t hesitate to contact us.' . PHP_EOL .
            'Thank you for choosing our services.';

        return [
            'title' => 'Congratulations!',
            'message' => new HtmlString($message),
        ];
    }
}

