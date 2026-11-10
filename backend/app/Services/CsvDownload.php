<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvDownload
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<scalar|null>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open the CSV output stream.');
            }

            try {
                if (fwrite($stream, "\xEF\xBB\xBF") !== 3 || fputcsv($stream, $headers, ',', '"', '') === false) {
                    throw new RuntimeException('Unable to write the CSV header.');
                }

                foreach ($rows as $row) {
                    if (fputcsv($stream, array_map($this->safeCell(...), $row), ',', '"', '') === false) {
                        throw new RuntimeException('Unable to write a CSV row.');
                    }
                }
            } finally {
                fclose($stream);
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCell(mixed $value): mixed
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (! is_string($value) || ! preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
            return $value;
        }

        return "'".$value;
    }
}
