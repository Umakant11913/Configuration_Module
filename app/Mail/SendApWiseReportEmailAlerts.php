<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SendApWiseReportEmailAlerts extends Notification
{
    use Queueable;

    protected $user;
    protected $apWiseReport;
    protected $frequency;
    public function __construct($user, $apWiseReport, $frequency)
    {
        $this->user = $user;
        $this->apWiseReport = $apWiseReport;
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
            ->subject('Ap Data Usage') // Subject remains unchanged
            ->greeting('Dear ' . new HtmlString($this->user->first_name) . ',')
            ->line(new HtmlString('<br>Your AP data usage report is now available. You can review the report in your dashboard for detailed insights on your resource utilization.<br><br>'))
            ->line(new HtmlString('For any further assistance, feel free to reach out.<br><br>'))
            ->line(new HtmlString('Best regards,<br>[Your Company Name] Support Team'));
    }

    public function toArray($notifiable)
    {
        return [
            'subject' => 'Ap Data Usage', // Subject remains unchanged
            'message' => '<p>Dear ' . new HtmlString($this->user->first_name) . ',</p>'
                . '<p>Your AP data usage report is now available. You can review the report in your dashboard for detailed insights on your resource utilization.</p>'
                . '<p>For any further assistance, feel free to reach out.</p>'
                . '<p>Best regards,<br>[Your Company Name] Support Team</p>'
            ];

    }

}
