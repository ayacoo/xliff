<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TYPO3\CMS\Core\Utility\CsvUtility;

class XlsxExportService
{
    public function buildExport(array $xliffItems, string $absoluteFilePath)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Translations EN');
        $spreadsheet->getActiveSheet()->getDefaultColumnDimension()->setAutoSize(true);
        $sheet = $spreadsheet->getActiveSheet();

        $row = 1;
        $sheet->setCellValueByColumnAndRow(1, $row, 'Element Id');
        $sheet->setCellValueByColumnAndRow(2, $row, 'Value');

        foreach ($xliffItems ?? [] as $item) {
            $row++;
            $id = (string)$item->attributes()->id;
            $value = (string)$item->source;

            $sheet->setCellValueByColumnAndRow(1, $row, $id);
            $sheet->setCellValueByColumnAndRow(2, $row, $value);
        }

        $exportFilename = str_replace('.xlf', '.xlsx', $absoluteFilePath);
        $writer = new Xlsx($spreadsheet);
        $writer->save($exportFilename);
    }
}
