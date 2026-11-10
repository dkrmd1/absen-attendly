<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\FirebasePushService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebasePushChannel
{
    public function send(object $notifiable, object $notification): void
    {
        if (! method_exists($notification, 'toDatabase') || ! $notifiable instanceof User) {
            return;
        }

        $payload = $notification->toDatabase($notifiable);
        $title = $payload['title'] ?? null;
        $body = $payload['message'] ?? null;
        if (! is_string($title) || ! is_string($body)) {
            return;
        }

        DB::afterCommit(function () use ($notifiable, $title, $body, $payload): void {
            try {
                app(FirebasePushService::class)->sendToUser(
                    $notifiable,
                    $title,
                    $body,
                    is_array($payload['data'] ?? null) ? $payload['data'] : [],
                );
            } catch (Throwable $exception) {
                Log::warning('Firebase push could not be prepared.', [
                    'user_id' => $notifiable->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
