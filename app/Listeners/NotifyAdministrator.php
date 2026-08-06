<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Notifications\NewUserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyAdministrator implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(UserCreated $event): void
    {
        $adminEmail = config('app.admin_email');

        if (! $adminEmail) {
            Log::warning('Administrator email not configured; skipping admin notification', [
                'user_id' => $event->user->id,
            ]);

            return;
        }

        Notification::route('mail', $adminEmail)
            ->notify(new NewUserRegistered($event->user));
    }

    public function failed(UserCreated $event, \Throwable $exception): void
    {
        Log::error('Failed to send administrator notification', [
            'job_uuid' => $this->job?->uuid(),
            'user_id' => $event->user->id,
            'notification_type' => NewUserRegistered::class,
            'attempt' => $this->attempts(),
            'exception_class' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
