<?php

namespace App\Notifications;

use App\Notifications\Channels\FirebasePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestWorkflowNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, int|string|null>  $data
     */
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly array $data,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FirebasePushChannel::class];
    }

    /**
     * @return array{title: string, message: string, data: array<string, int|string|null>}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
