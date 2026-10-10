<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The email with the link for choosing a new password. The link works once and expires. */
class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false));
        $minutes = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject('Reset your TestaCBT password')
            ->greeting('Hello ' . explode(' ', trim($notifiable->name))[0] . ',')
            ->line('We received a request to reset the password for your TestaCBT account.')
            ->action('Choose a new password', $url)
            ->line("This link works once and expires in {$minutes} minutes.")
            ->line('If you did not ask for this, you can ignore this email. Your password stays the same.');
    }
}
