<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Notifications\AccountCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendAccountCreatedEmail implements ShouldQueue
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
        $event->user->notify(new AccountCreated($event->user));
    }

    public function failed(UserCreated $event, \Throwable $exception): void
    {
        Log::error('Failed to send account-created email', [
            'job_uuid' => $this->job?->uuid(),
            'user_id' => $event->user->id,
            'notification_type' => AccountCreated::class,
            'attempt' => $this->attempts(),
            'exception_class' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
