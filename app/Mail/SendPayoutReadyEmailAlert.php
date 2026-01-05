<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SendPayoutReadyEmailAlert extends Notification
{
    use Queueable;

    protected $user;
    protected $payout;
    protected $frequency;
    public function __construct($user, $payout, $frequency)
    {
        $this->user = $user;
        $this->payout = $payout;
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
            ->subject('Payout Ready for Processing')
            ->greeting('Dear ' . new HtmlString($this->user->first_name) . ',')
            ->line(new HtmlString('<br>Your payout is now ready for processing. Please review your account for details and let us know if there are any discrepancies.<br><br>'))
            ->line(new HtmlString('Best regards,<br>[Your Company Name] Finance Team'));
    }

    public function toArray($notifiable)
    {
        return [
            'subject' => 'Payout Ready for Processing',
            'message' => '<p>Dear ' . new HtmlString($this->user->first_name) . ',</p>'
                . '<p>Your payout is now ready for processing. Please review your account for details and let us know if there are any discrepancies.</p>'
                . '<p>Best regards,<br>[Your Company Name] Finance Team</p>'
        ];
    }

}
