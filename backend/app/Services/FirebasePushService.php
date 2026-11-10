<?php

namespace App\Services;

use App\Models\FirebaseMessagingSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FirebasePushService
{
    /**
     * @return array{apiKey: string, appId: string, messagingSenderId: string, projectId: string}|null
     */
    public function clientConfig(): ?array
    {
        $settings = FirebaseMessagingSetting::query()->first();

        if (! $settings?->enabled || ! $settings->service_account) {
            return null;
        }

        return [
            'apiKey' => $settings->api_key,
            'appId' => $settings->app_id,
            'messagingSenderId' => $settings->messaging_sender_id,
            'projectId' => $settings->project_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        $settings = FirebaseMessagingSetting::query()->first();
        if (! $settings?->enabled || ! $settings->service_account) {
            return 0;
        }

        $devices = $user->fcmDevices()->get();
        if ($devices->isEmpty()) {
            return 0;
        }

        $messaging = (new Factory)
            ->withServiceAccount($settings->service_account)
            ->createMessaging();

        $messageData = [];
        foreach ($data as $key => $value) {
            $messageData[(string) $key] = is_scalar($value)
                ? (string) $value
                : json_encode($value, JSON_THROW_ON_ERROR);
        }

        $sent = 0;
        foreach ($devices as $device) {
            try {
                $message = CloudMessage::new()
                    ->toToken($device->token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData($messageData);
                $messaging->send($message);
                $sent++;
            } catch (NotFound $exception) {
                $device->delete();
                Log::info('Removed an unregistered Firebase device token.', [
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                ]);
            } catch (Throwable $exception) {
                Log::warning('Firebase push delivery failed.', [
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }
}
