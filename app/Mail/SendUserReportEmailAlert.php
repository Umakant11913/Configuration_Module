<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SendUserReportEmailAlert extends Notification
{
    use Queueable;

    protected $user;
    protected $allUserReports;
    protected $frequency;
    public function __construct($user, $allUserReports, $frequency)
    {
        $this->user = $user;
        $this->allUserReports = $allUserReports;
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
            ->subject('Usage Report Available')
            ->greeting('Dear ' . new HtmlString($this->user->first_name) . ',')
            ->line(new HtmlString('<br>Your monthly usage report is now available. You can review the report in your dashboard for detailed insights on your resource utilization.<br><br>'))
            ->line(new HtmlString('For any further assistance, feel free to reach out.<br><br>'))
            ->line(new HtmlString('Best regards,<br>[Your Company Name] Support Team'));
    }

    public function toArray($notifiable)
    {
        return [
            'subject' => 'Usage Report Available',
            'message' => '<p>Dear ' . new HtmlString($this->user->first_name) . ',</p>'
                . '<p>Your monthly usage report is now available. You can review the report in your dashboard for detailed insights on your resource utilization.</p>'
                . '<p>For any further assistance, feel free to reach out.</p>'
                . '<p>Best regards,<br>[Your Company Name] Support Team</p>'
        ];
    }


}
