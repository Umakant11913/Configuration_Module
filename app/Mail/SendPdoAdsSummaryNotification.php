<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SendPdoAdsSummaryNotification extends Notification
{
    use Queueable;

    private $user, $userImpression, $ads;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $userImpression, $ads)
    {
        $this->user = $user;
        $this->userImpression = $userImpression;
        $this->ads = $ads;
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
        $message = (new MailMessage);
        //Log::info('Impression: ' . $this->ads->impressions);
        //Log::info('Impression Count: ' . $this->ads->impression_counts);
        //Log::info('Suspend: ' . $this->ads->suspend);
        //Log::info('Expired: ' . $this->ads->expiry_date);
        $date = Carbon::today()->format('Y-m-d H:i:s');

        //Log::info($date);

        if ($this->ads->impressions === $this->ads->impression_counts) {
            $message->subject('Ads impression limit Reached')
                ->greeting('Hello! ' . new HtmlString($this->user->first_name))
                ->line(new HtmlString('Your ads have been suspended because the impression limit has been reached. Your total impressions: ' . $this->ads->impressions . '.'))
                ->line(new HtmlString('You can disable the send summary report alerts anytime by going to https://pdo.pmwani.net/advertisements. For more details, please log in.'));
        } elseif ($this->ads->suspend === 1) {
            $message->subject('Ads Suspended')
                ->greeting('Hello! ' . new HtmlString($this->user->first_name))
                ->line(new HtmlString('Your ads have been suspended because they have been manually suspended. Your total impressions: ' . $this->ads->impressions . '.'))
                ->line(new HtmlString('You can disable the send summary report alerts anytime by going to https://pdo.pmwani.net/advertisements. For more details, please log in.'));
        } elseif ($this->ads->expiry_date <= $date) {
            //Log::info('date--------> ' . $date);
            $message->subject('Ads Expired')
                ->greeting('Hello! ' . new HtmlString($this->user->first_name))
                ->line(new HtmlString('Your ads have been suspended because they have expired. Your total impressions: ' . $this->ads->expiry_date . '.'))
                ->line(new HtmlString('You can disable the send summary report alerts anytime by going to https://pdo.pmwani.net/advertisements. For more details, please log in.'));
        } else {
            $message->subject('Daily Ads Summary')
                ->greeting('Hello! ' . new HtmlString($this->user->first_name))
                ->line(new HtmlString('Here is the summary of your ads performance for today:'))
                ->line(new HtmlString('Total Impressions: ' . $this->ads->impressions . '.'))
                ->line(new HtmlString('Total Clicks: ' . $this->ads->clicks . '.'))
                ->line(new HtmlString('You can disable the send summary report alerts anytime by going to https://pdo.pmwani.net/advertisements. For more details, please log in.'));
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
        if ($this->ads->impressions == $this->ads->impression_counts) {
            $subject = 'Ads impression limit Reached';
            $message = 'Your ads have been suspended because the impression limit has been reached. Your total impressions: ' . $this->ads->impressions . '. You can disable the send summary report alerts anytime by going to https://pdo.pmwani.net/advertisements. For more details, please log in.';
        } elseif ($this->ads->suspend == 1) {
            $subject = 'Ads Suspended';
            $message = 'Your ads have been suspended because they have been manually suspended. Your total impressions: ' . $this->ads->impressions . '. You can disable the send summary report alerts anytime by going to https://pdo.pmwani.net/advertisements. For more details, please log in.';
        } elseif (Carbon::parse($this->ads->expiry_date)->lte(Carbon::today())) {
            $subject = 'Ads Expired';
            $expiredDate = Carbon::parse($this->ads->expiry_date)->format('Y-m-d H:i:s');
            $message = 'Your ads have been suspended because they have expired. Expired: ' . $expiredDate . '. You can disable the send summary report alerts anytime by going to https://pdo.pmwani.net/advertisements. For more details, please log in.';
        } else {
            $subject = 'Daily Ads Summary';
            $message = 'Here is the summary of your ads performance for today: Total Impressions: ' . $this->ads->impressions . '. Total Clicks: ' . $this->ads->clicks . '. You can disable the send summary report alerts anytime by going to https://pdo.pmwani.net/advertisements. For more details, please log in.';
        }
        return [
            'title' => $subject,
            'message' => $message
        ];
    }
}

