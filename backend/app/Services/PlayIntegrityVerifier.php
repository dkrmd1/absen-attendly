<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class PlayIntegrityVerifier
{
    public function __construct(private readonly GooglePlayIntegrityDecoder $decoder) {}

    public function isEnabled(): bool
    {
        return (bool) config('play_integrity.enabled');
    }

    public function verify(
        ?string $integrityToken,
        int $userId,
        string $action,
        int|float|string|null $latitude,
        int|float|string|null $longitude,
        ?string $selfieHash = null,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $integrityToken) {
            $this->reject('Token integritas perangkat tidak tersedia.');
        }

        try {
            $payload = $this->decoder->decode($integrityToken);
        } catch (Exception $exception) {
            Log::warning('Play Integrity verification service unavailable.', [
                'exception' => $exception::class,
            ]);
            $this->unavailable();
        }

        $requestDetails = $payload['requestDetails'] ?? null;
        if (! is_array($requestDetails)) {
            $this->reject('Token integritas perangkat tidak valid atau sudah kedaluwarsa.');
        }

        $expectedHash = self::requestHash(
            $userId,
            $action,
            $latitude,
            $longitude,
            $selfieHash,
        );
        $timestampMillis = filter_var(
            $requestDetails['timestampMillis'] ?? null,
            FILTER_VALIDATE_INT,
        );
        $ageMillis = ! is_int($timestampMillis)
            ? PHP_INT_MAX
            : (int) floor(microtime(true) * 1000) - $timestampMillis;
        $maxAgeMillis = (int) config('play_integrity.max_token_age_seconds', 120) * 1000;

        if (
            ($requestDetails['requestPackageName'] ?? null) !== config('play_integrity.package_name')
            || ! is_string($requestDetails['requestHash'] ?? null)
            || ! hash_equals($expectedHash, $requestDetails['requestHash'])
            || $ageMillis < -30_000
            || $ageMillis > $maxAgeMillis
        ) {
            $this->reject('Token integritas perangkat tidak valid atau sudah kedaluwarsa.');
        }

        $appIntegrity = $payload['appIntegrity'] ?? null;
        $deviceIntegrity = $payload['deviceIntegrity'] ?? null;
        if (! is_array($appIntegrity) || ! is_array($deviceIntegrity)) {
            $this->reject('Perangkat atau aplikasi tidak memenuhi pemeriksaan integritas.');
        }

        $appVerdict = $appIntegrity['appRecognitionVerdict'] ?? null;
        $deviceVerdicts = $deviceIntegrity['deviceRecognitionVerdict'] ?? [];

        if (
            $appVerdict !== 'PLAY_RECOGNIZED'
            || ! is_array($deviceVerdicts)
            || ! in_array('MEETS_DEVICE_INTEGRITY', $deviceVerdicts, true)
        ) {
            $this->reject('Perangkat atau aplikasi tidak memenuhi pemeriksaan integritas.');
        }
    }

    public static function requestHash(
        int $userId,
        string $action,
        int|float|string|null $latitude,
        int|float|string|null $longitude,
        ?string $selfieHash = null,
    ): string {
        $coordinates = static fn (int|float|string|null $value): string => $value === null
            ? '-'
            : number_format((float) $value, 7, '.', '');
        $request = implode('|', [
            $action,
            (string) $userId,
            $coordinates($latitude),
            $coordinates($longitude),
            $selfieHash ?? '-',
        ]);

        return rtrim(strtr(base64_encode(hash('sha256', $request, true)), '+/', '-_'), '=');
    }

    private function reject(string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], 403));
    }

    private function unavailable(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Pemeriksaan integritas sedang tidak tersedia. Coba lagi nanti.',
        ], 503));
    }
}
