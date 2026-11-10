<?php

namespace App\Notifications;

use App\Notifications\Channels\FirebasePushChannel;
use Illuminate\Notifications\Notification;

class AttendanceWorkflowNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $data
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
     * @return array{title: string, message: string, data: array<string, mixed>}
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
