<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Export;

use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CsvExportService
{
    public function buildExport(array $xliffItems, string $absoluteFilePath): void
    {
        $csvLines = [];
        $dataRow['id'] = 'Element ID';
        $dataRow['value'] = 'Value';
        $csvLines[] = CsvUtility::csvValues($dataRow, ',', '"');

        foreach ($xliffItems ?? [] as $item) {
            $dataRow['id'] = (string)$item->attributes()->id;
            $dataRow['value'] = (string)$item->source;
            $csvLines[] = CsvUtility::csvValues($dataRow, ',', '"');
        }

        $exportFilename = str_replace('.xlf', '.csv', $absoluteFilePath);
        GeneralUtility::writeFile($exportFilename, implode(CRLF, $csvLines));
    }
}
