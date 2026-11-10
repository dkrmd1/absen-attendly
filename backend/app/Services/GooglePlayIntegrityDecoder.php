<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GooglePlayIntegrityDecoder
{
    /**
     * @return array<string, mixed>
     */
    public function decode(string $integrityToken): array
    {
        $packageName = (string) config('play_integrity.package_name');
        $credentialsPath = (string) config('play_integrity.service_account_path');

        if ($packageName === '' || ! is_file($credentialsPath)) {
            throw new RuntimeException('Play Integrity server credentials are not configured.');
        }

        $credentials = json_decode(
            (string) file_get_contents($credentialsPath),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        if (! is_array($credentials)) {
            throw new RuntimeException('Google service account credentials are invalid.');
        }
        $accessToken = $this->accessToken($credentials);

        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->timeout(10)
            ->post(
                "https://playintegrity.googleapis.com/v1/{$packageName}:decodeIntegrityToken",
                ['integrityToken' => $integrityToken],
            );

        if (! $response->successful()) {
            throw new RuntimeException('Google Play Integrity token decoding failed.');
        }

        $payload = $response->json('tokenPayloadExternal');

        if (! is_array($payload)) {
            throw new RuntimeException('Google returned an invalid Play Integrity payload.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function accessToken(array $credentials): string
    {
        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;
        if (! is_string($clientEmail) || ! is_string($privateKey)) {
            throw new RuntimeException('Google service account credentials are invalid.');
        }

        return cache()->remember(
            'play_integrity.oauth_token.'.hash('sha256', $clientEmail),
            now()->addMinutes(54),
            function () use ($clientEmail, $privateKey): string {
                $issuedAt = time();
                $assertion = $this->signJwt([
                    'iss' => $clientEmail,
                    'scope' => 'https://www.googleapis.com/auth/playintegrity',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $issuedAt,
                    'exp' => $issuedAt + 3600,
                ], $privateKey);

                $response = Http::asForm()
                    ->timeout(10)
                    ->post('https://oauth2.googleapis.com/token', [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $assertion,
                    ]);

                $response->throw();
                $token = $response->json('access_token');
                if (! is_string($token) || $token === '') {
                    throw new RuntimeException('Google access token could not be obtained.');
                }

                return $token;
            },
        );
    }

    /**
     * @param  array<string, int|string>  $claims
     */
    private function signJwt(array $claims, string $privateKey): string
    {
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $unsignedToken = $encode(json_encode(
            ['alg' => 'RS256', 'typ' => 'JWT'],
            JSON_THROW_ON_ERROR,
        )).'.'.$encode(json_encode($claims, JSON_THROW_ON_ERROR));
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false || ! openssl_sign($unsignedToken, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Google service account JWT signing failed.');
        }

        return $unsignedToken.'.'.$encode($signature);
    }
}
