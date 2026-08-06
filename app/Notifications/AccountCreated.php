<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $user
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome — your account has been created')
            ->greeting("Hello, {$this->user->name}!")
            ->line('Your account has been created successfully.')
            ->line('You can now sign in using your registered email address.')
            ->line('If you did not create this account, please contact support immediately.');
    }
}
