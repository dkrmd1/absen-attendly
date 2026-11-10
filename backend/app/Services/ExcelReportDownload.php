<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelReportDownload
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<scalar|null>>  $rows
     */
    public function download(
        string $filename,
        string $title,
        string $subtitle,
        array $headers,
        iterable $rows,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($title, $subtitle, $headers, $rows): void {
            $temporaryFile = tempnam(sys_get_temp_dir(), 'attendly-report-');

            if ($temporaryFile === false) {
                throw new RuntimeException('Unable to create a temporary XLSX file.');
            }

            try {
                $spreadsheet = new Spreadsheet;
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Laporan');
                $lastColumn = $this->columnName(count($headers));

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->setCellValue('A2', $subtitle);
                $sheet->mergeCells("A3:{$lastColumn}3");
                $sheet->setCellValue('A3', 'Dibuat pada '.now()->format('d/m/Y H:i'));

                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '123B5D']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
                    'font' => ['size' => 10, 'color' => ['rgb' => '24445F']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F2F8']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("A3:{$lastColumn}3")->applyFromArray([
                    'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '64748B']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->mergeCells("A4:{$lastColumn}4");
                $sheet->setCellValue('A4', 'Tips: Gunakan ikon filter pada judul kolom untuk mencari, menyaring, atau mengurutkan data.');
                $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => '0F6B78']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F9FA']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(34);
                $sheet->getRowDimension(2)->setRowHeight(23);
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->getRowDimension(4)->setRowHeight(22);

                foreach ($headers as $index => $header) {
                    $sheet->setCellValue($this->columnName($index + 1).'5', $header);
                }

                $sheet->getStyle("A5:{$lastColumn}5")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F6B78']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->getRowDimension(5)->setRowHeight(32);

                $rowNumber = 6;

                foreach ($rows as $row) {
                    foreach (array_values($row) as $index => $value) {
                        $cell = $this->columnName($index + 1).$rowNumber;

                        if ($value instanceof \Stringable) {
                            $value = (string) $value;
                        }

                        if (is_string($value)) {
                            $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
                        } else {
                            $sheet->setCellValue($cell, $value);
                        }
                    }

                    $rowNumber++;
                }

                $lastRow = max(5, $rowNumber - 1);
                if ($lastRow > 5) {
                    $tableStyle = new TableStyle;
                    $tableStyle->setTheme(TableStyle::TABLE_STYLE_MEDIUM2);
                    $tableStyle->setShowRowStripes(true);

                    $table = new Table("A5:{$lastColumn}{$lastRow}", 'AttendlyReport');
                    $table->setStyle($tableStyle);
                    $sheet->addTable($table);
                } else {
                    $sheet->setAutoFilter("A5:{$lastColumn}5");
                }
                $sheet->freezePane('A6');
                $sheet->getStyle("A6:{$lastColumn}{$lastRow}")->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);

                foreach ($headers as $index => $header) {
                    $width = min(max(mb_strlen($header) + 4, 14), 30);

                    if (in_array($header, [
                        'Deskripsi',
                        'Alasan',
                        'Catatan review',
                        'Catatan review selfie',
                        'Catatan keputusan akhir',
                        'Catatan tinjauan',
                    ], true)) {
                        $width = 38;
                    }

                    if ($header === 'Email') {
                        $width = 28;
                    }

                    $sheet->getColumnDimension($this->columnName($index + 1))->setWidth($width);
                }

                $sheet->getPageSetup()
                    ->setOrientation('landscape')
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 5);
                $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.35)->setRight(0.35);

                $spreadsheet->getProperties()
                    ->setCreator('Attendly')
                    ->setTitle($title)
                    ->setSubject($subtitle);

                (new Xlsx($spreadsheet))->save($temporaryFile);
                $spreadsheet->disconnectWorksheets();

                if (readfile($temporaryFile) === false) {
                    throw new RuntimeException('Unable to stream the XLSX report.');
                }
            } finally {
                if (is_file($temporaryFile) && ! unlink($temporaryFile)) {
                    throw new RuntimeException('Unable to remove the temporary XLSX file.');
                }
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function columnName(int $number): string
    {
        $column = '';

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $column = chr(65 + $remainder).$column;
            $number = intdiv($number - 1, 26);
        }

        return $column;
    }
}
