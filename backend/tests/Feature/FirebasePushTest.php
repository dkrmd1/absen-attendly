<?php

namespace Tests\Feature;

use App\Models\FcmDevice;
use App\Models\FirebaseMessagingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FirebasePushTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_config_exposes_only_public_client_settings_when_enabled(): void
    {
        $this->getJson('/api/push/config')
            ->assertOk()
            ->assertJsonPath('data', null);

        FirebaseMessagingSetting::query()->create([
            'project_id' => 'attendly-demo',
            'api_key' => 'public-api-key',
            'app_id' => '1:123:android:abc',
            'messaging_sender_id' => '123',
            'service_account' => [
                'type' => 'service_account',
                'client_email' => 'firebase@example.test',
                'private_key' => 'server-private-key',
            ],
            'enabled' => true,
        ]);

        $this->getJson('/api/push/config')
            ->assertOk()
            ->assertJsonPath('data.projectId', 'attendly-demo')
            ->assertJsonPath('data.apiKey', 'public-api-key')
            ->assertJsonMissingPath('data.service_account')
            ->assertDontSee('server-private-key');
    }

    public function test_authenticated_users_can_register_and_remove_only_their_own_devices(): void
    {
        $this->postJson('/api/push/devices', [
            'token' => 'device-token',
            'platform' => 'android',
        ])->assertUnauthorized();

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownerToken = $owner->createToken('test')->plainTextToken;
        $otherToken = $otherUser->createToken('test')->plainTextToken;

        $this->withToken($ownerToken)->postJson('/api/push/devices', [
            'token' => 'device-token',
            'platform' => 'android',
        ])->assertOk();

        $this->assertDatabaseHas('fcm_devices', [
            'user_id' => $owner->id,
            'token' => 'device-token',
            'platform' => 'android',
        ]);
        $deviceId = FcmDevice::query()->value('id');

        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->deleteJson('/api/push/devices', [
            'token' => 'device-token',
        ])->assertOk();
        $this->assertDatabaseHas('fcm_devices', ['id' => $deviceId]);

        $this->app['auth']->forgetGuards();
        $this->withToken($ownerToken)->deleteJson('/api/push/devices', [
            'token' => 'device-token',
        ])->assertOk();
        $this->assertDatabaseMissing('fcm_devices', ['token' => 'device-token']);
    }

    public function test_only_admin_can_manage_encrypted_firebase_credentials(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'employee']))
            ->get('/admin/settings/firebase-messaging')
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get('/admin/settings/firebase-messaging')
            ->assertOk()
            ->assertSee('Pengaturan Firebase Cloud Messaging');

        $credentials = [
            'type' => 'service_account',
            'project_id' => 'attendly-demo',
            'client_email' => 'firebase@example.test',
            'private_key' => 'secret-private-key',
        ];

        $this->from('/admin/settings/firebase-messaging')
            ->actingAs($admin)
            ->put('/admin/settings/firebase-messaging', [
                'project_id' => 'attendly-demo',
                'api_key' => 'public-api-key',
                'app_id' => '1:123:android:abc',
                'messaging_sender_id' => '123',
                'enabled' => '1',
                'service_account' => UploadedFile::fake()->createWithContent(
                    'service-account.json',
                    json_encode($credentials, JSON_THROW_ON_ERROR),
                ),
            ])
            ->assertRedirect('/admin/settings/firebase-messaging')
            ->assertSessionHas('success');

        $setting = FirebaseMessagingSetting::query()->firstOrFail();
        $this->assertSame($credentials, $setting->service_account);
        $this->assertStringNotContainsString(
            'secret-private-key',
            (string) DB::table('firebase_messaging_settings')
                ->where('id', $setting->id)
                ->value('service_account'),
        );
        $this->getJson('/api/push/config')
            ->assertOk()
            ->assertJsonMissingPath('data.private_key')
            ->assertDontSee('secret-private-key');
    }

    public function test_enabled_configuration_requires_a_valid_service_account(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from('/admin/settings/firebase-messaging')
            ->put('/admin/settings/firebase-messaging', [
                'project_id' => 'attendly-demo',
                'api_key' => 'public-api-key',
                'app_id' => '1:123:android:abc',
                'messaging_sender_id' => '123',
                'enabled' => '1',
            ])
            ->assertSessionHasErrors('service_account');

        $this->assertDatabaseCount('firebase_messaging_settings', 0);
    }
}
