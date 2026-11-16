<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public int $userId,
        public string $type,
        public string $channel,
        public array $data,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $log = NotificationLog::create([
            'user_id' => $this->userId,
            'type' => $this->type,
            'channel' => $this->channel,
            'data' => $this->data,
            'status' => 'pending',
        ]);

        try {
            match ($this->channel) {
                'email' => $this->sendEmail($user),
                'fcm' => $this->sendFcm($user),
                default => null,
            };

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function sendEmail(User $user): void
    {
        Mail::raw(
            json_encode($this->data, JSON_PRETTY_PRINT),
            fn ($message) => $message->to($user->email)->subject($this->type),
        );
    }

    protected function sendFcm(User $user): void
    {
        Log::info('FCM notification', [
            'user_id' => $user->id,
            'type' => $this->type,
            'data' => $this->data,
        ]);
    }
}
