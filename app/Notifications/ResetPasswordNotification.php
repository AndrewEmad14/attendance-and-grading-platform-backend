<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token, public string $email) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = env('FRONTEND_URL').'?token='.$this->token.'&email='.urlencode($this->email);

        return (new MailMessage)
            ->subject('Activate Your Account')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You have been provisioned an account on the ITI Attendance & Grading Platform.')
            ->line('Click the button below to set your password and activate your account.')
            ->action('Activate Account', $url)
            ->line('This link will expire in 24 hours.')
            ->line('If you did not expect this email, please ignore it.');
    }
}
