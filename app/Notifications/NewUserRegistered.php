<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegistered extends Notification
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
        $role = $this->user->role instanceof UserRole
            ? $this->user->role->value
            : (string) $this->user->role;

        return (new MailMessage)
            ->subject('New user registered')
            ->greeting('Administrator notification')
            ->line('A new user account has been created.')
            ->line("Name: {$this->user->name}")
            ->line("Email: {$this->user->email}")
            ->line("Role: {$role}")
            ->line("Registered at: {$this->user->created_at?->toIso8601String()}");
    }
}
