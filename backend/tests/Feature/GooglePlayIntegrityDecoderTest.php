<?php

namespace Tests\Feature;

use App\Services\GooglePlayIntegrityDecoder;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GooglePlayIntegrityDecoderTest extends TestCase
{
    public function test_it_authenticates_with_service_account_and_decodes_the_google_token(): void
    {
        $keyOptions = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ];
        if (PHP_OS_FAMILY === 'Windows' && ! getenv('OPENSSL_CONF')) {
            $xamppOpenSslConfig = dirname(PHP_BINARY).'\extras\ssl\openssl.cnf';
            if (is_file($xamppOpenSslConfig)) {
                $keyOptions['config'] = $xamppOpenSslConfig;
            }
        }
        $key = openssl_pkey_new($keyOptions);
        $this->assertNotFalse($key);
        $exportOptions = isset($keyOptions['config'])
            ? ['config' => $keyOptions['config']]
            : [];
        $this->assertTrue(openssl_pkey_export($key, $privateKey, null, $exportOptions));

        $credentialsPath = tempnam(sys_get_temp_dir(), 'attendly-play-integrity-');
        $this->assertNotFalse($credentialsPath);
        file_put_contents($credentialsPath, json_encode([
            'type' => 'service_account',
            'client_email' => 'attendly@example.test',
            'private_key' => $privateKey,
        ], JSON_THROW_ON_ERROR));

        config([
            'play_integrity.package_name' => 'id.co.attendly.mobile',
            'play_integrity.service_account_path' => $credentialsPath,
        ]);
        Http::fake(function (ClientRequest $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                $this->assertSame(
                    'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    $request->data()['grant_type'] ?? null,
                );
                $assertion = $request->data()['assertion'] ?? '';
                $this->assertCount(3, explode('.', $assertion));

                return Http::response(['access_token' => 'server-access-token'], 200);
            }

            if ($request->url() === 'https://playintegrity.googleapis.com/v1/id.co.attendly.mobile:decodeIntegrityToken') {
                $this->assertSame('Bearer server-access-token', $request->header('Authorization')[0] ?? null);
                $this->assertSame('opaque-integrity-token', $request->data()['integrityToken'] ?? null);

                return Http::response([
                    'tokenPayloadExternal' => [
                        'appIntegrity' => ['appRecognitionVerdict' => 'PLAY_RECOGNIZED'],
                    ],
                ], 200);
            }

            throw new RuntimeException('Unexpected Google API endpoint.');
        });

        try {
            $payload = app(GooglePlayIntegrityDecoder::class)->decode('opaque-integrity-token');
        } finally {
            unlink($credentialsPath);
        }

        $this->assertSame(
            'PLAY_RECOGNIZED',
            $payload['appIntegrity']['appRecognitionVerdict'],
        );
        Http::assertSentCount(2);
    }
}
