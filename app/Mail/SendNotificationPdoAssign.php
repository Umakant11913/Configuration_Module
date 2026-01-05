<?php

namespace App\Mail;
use Carbon\Carbon;
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

class SendNotificationPdoAssign extends Notification
{
    use Queueable;
    private $user;
    private $pdoPlan;
    private $credits;
    public function __construct($user, $pdoPlan, $credits)
    {
        $this->user = $user;
        $this->pdoPlan = $pdoPlan;
        $this->credits = $credits;
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
    public function toMail( object $notifiable) :MailMessage
    {
        return (new MailMessage)

            ->subject('Congratulations!')
            ->greeting('Hello! '.new HtmlString($this->user->first_name))
            ->line('We are pleased to inform you that you have been successfully added to a PDO.')
            ->line('Below are the details of your PDO plan:: ' . $this->pdoPlan->name . ', Commission: ' . $this->pdoPlan->commission)
            ->line('Service fee for each credit: ' . $this->pdoPlan->service_fee . ', Contract Length: ' . $this->pdoPlan->contract_length . ' Month(s)')
            ->line('Credits: (1 credit = 1 AP) ' . $this->credits->credits .' Validity Period: ' . $this->pdoPlan->validity_period . ' Month(s)')
            ->line('SMS Quota (1Ap): ' . $this->pdoPlan->sms_quota . ', Grace Period: ' . $this->pdoPlan->grace_period . ' Month(s)')
            ->line('Note :- '.' "Note: Your subscription will start as soon as an AP is active/assigned to you. ')
            ->line(new HtmlString('For more details, please log in to your account.'));
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
            'message'=>'Hello! '.new HtmlString($this->user->first_name) .'Your payout has been approved  successfully. The payout amount is '.new HtmlString('For more details, please login to your account.')
        ];
    }


}
