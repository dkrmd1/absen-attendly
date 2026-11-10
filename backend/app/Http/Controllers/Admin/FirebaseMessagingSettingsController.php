<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FcmDevice;
use App\Models\FirebaseMessagingSetting;
use App\Services\FirebasePushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use Throwable;

class FirebaseMessagingSettingsController extends Controller
{
    public function index(): View
    {
        $settings = FirebaseMessagingSetting::query()->first();

        return view('admin.firebase-messaging.index', [
            'settings' => $settings,
            'deviceCount' => FcmDevice::query()->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
            'app_id' => ['required', 'string', 'max:255'],
            'messaging_sender_id' => ['required', 'string', 'max:255'],
            'service_account' => ['nullable', 'file', 'max:20', 'mimes:json'],
            'enabled' => ['nullable', 'boolean'],
            'remove_service_account' => ['nullable', 'boolean'],
        ]);

        $settings = FirebaseMessagingSetting::query()->first() ?? new FirebaseMessagingSetting;
        $credentials = $settings->service_account;

        if ($request->boolean('remove_service_account')) {
            $credentials = null;
        }

        if ($request->hasFile('service_account')) {
            try {
                $contents = file_get_contents($request->file('service_account')->getPathname());
                if ($contents === false) {
                    throw new JsonException('Unable to read service account file.');
                }
                $credentials = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages([
                    'service_account' => 'File service account harus berisi JSON yang valid.',
                ]);
            }

            if (
                ! is_array($credentials)
                || ($credentials['type'] ?? null) !== 'service_account'
                || ! is_string($credentials['client_email'] ?? null)
                || ! is_string($credentials['private_key'] ?? null)
                || ($credentials['project_id'] ?? null) !== $validated['project_id']
            ) {
                throw ValidationException::withMessages([
                    'service_account' => 'Service account harus valid dan project_id-nya harus cocok.',
                ]);
            }
        }

        if (
            is_array($credentials)
            && (
                ($credentials['type'] ?? null) !== 'service_account'
                || ! is_string($credentials['client_email'] ?? null)
                || ! is_string($credentials['private_key'] ?? null)
                || ($credentials['project_id'] ?? null) !== $validated['project_id']
            )
        ) {
            throw ValidationException::withMessages([
                'service_account' => 'Service account harus valid dan project_id-nya harus cocok.',
            ]);
        }

        if ($request->boolean('enabled') && ! is_array($credentials)) {
            throw ValidationException::withMessages([
                'service_account' => 'Unggah service account sebelum mengaktifkan FCM.',
            ]);
        }

        $settings->fill([
            'project_id' => $validated['project_id'],
            'api_key' => $validated['api_key'],
            'app_id' => $validated['app_id'],
            'messaging_sender_id' => $validated['messaging_sender_id'],
            'service_account' => $credentials,
            'enabled' => $request->boolean('enabled'),
        ])->save();

        return back()->with('success', 'Pengaturan Firebase berhasil disimpan.');
    }

    public function sendTest(Request $request, FirebasePushService $push): RedirectResponse
    {
        try {
            $sent = $push->sendToUser(
                $request->user(),
                'Notifikasi uji Attendly',
                'Firebase Cloud Messaging berhasil terhubung.',
                ['type' => 'test'],
            );
        } catch (Throwable $exception) {
            Log::warning('Firebase test push failed.', ['error' => $exception->getMessage()]);

            throw ValidationException::withMessages([
                'firebase' => 'Pengiriman gagal. Periksa konfigurasi dan log server.',
            ]);
        }

        if ($sent === 0) {
            throw ValidationException::withMessages([
                'firebase' => 'FCM belum aktif atau belum ada perangkat admin yang terdaftar.',
            ]);
        }

        return back()->with('success', "Notifikasi uji dikirim ke {$sent} perangkat.");
    }
}
