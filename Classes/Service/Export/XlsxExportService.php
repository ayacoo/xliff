<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class XlsxExportService implements AbstractExportServiceInterface
{
    protected array $xliffItems = [];

    protected string $extensionName = '';

    protected bool $singleFileExport = false;

    /**
     * @param string $extensionName
     * @param bool $singleFileExport
     */
    public function __construct(string $extensionName = '', bool $singleFileExport = false)
    {
        $this->extensionName = $extensionName;
        $this->singleFileExport = $singleFileExport;
    }

    /**
     * @param array $xliffItems
     */
    public function setXliffItems(array $xliffItems): void
    {
        $this->xliffItems = $xliffItems;
    }

    public function save(): void
    {
        if ($this->singleFileExport === true) {
            $this->buildSingleFileExport();
        } else {
            $this->buildMultiFileExport();
        }
    }

    protected function buildMultiFileExport(): void
    {
        foreach ($this->xliffItems as $absoluteFilePath => $transUnitItems) {
            $spreadsheet = new Spreadsheet();
            $spreadsheet->getActiveSheet()->setTitle('Translations');
            $spreadsheet->getActiveSheet()->getDefaultColumnDimension()->setAutoSize(true);
            $sheet = $spreadsheet->getActiveSheet();

            $row = 1;
            $sheet->setCellValueByColumnAndRow(1, $row, 'Element ID');
            $sheet->setCellValueByColumnAndRow(2, $row, 'Value');
            $sheet->getCellByColumnAndRow(1, $row)->getStyle()->getFont()->setBold(true);
            $sheet->getCellByColumnAndRow(2, $row)->getStyle()->getFont()->setBold(true);

            foreach ($transUnitItems ?? [] as $item) {
                $row++;
                $id = (string)$item->attributes()->id;
                $value = (string)$item->source;

                $sheet->setCellValueByColumnAndRow(1, $row, $id);
                $sheet->setCellValueByColumnAndRow(2, $row, $value);
            }

            foreach (range('A', 'B') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            $exportFilename = str_replace('.xlf', '.xlsx', $absoluteFilePath);
            $writer = new Xlsx($spreadsheet);
            $writer->save($exportFilename);
        }
    }

    protected function buildSingleFileExport(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Translations');
        $spreadsheet->getActiveSheet()->getDefaultColumnDimension()->setAutoSize(true);
        $sheet = $spreadsheet->getActiveSheet();

        $row = 1;
        $columnIndex = 1;
        $sheet->setCellValueByColumnAndRow(1, $row, 'Element ID');
        $sheet->getCellByColumnAndRow(1, $row)->getStyle()->getFont()->setBold(true);
        $allLanguageKeys = [];

        foreach ($this->xliffItems as $localLangContent) {
            $languageKeys = array_keys($localLangContent);
            foreach ($languageKeys as $languageKey) {
                $languageKey = strtolower($languageKey);
                $allLanguageKeys[$languageKey] = $languageKey;
            }
        }

        ksort($allLanguageKeys);

        $columns = [];
        foreach ($allLanguageKeys as $languageKey) {
            $columnIndex++;
            $sheet->setCellValueByColumnAndRow($columnIndex, $row, $languageKey);
            $sheet->getCellByColumnAndRow($columnIndex, $row)->getStyle()->getFont()->setBold(true);
            $columns[$languageKey] = $columnIndex;
        }

        $endLetter = $this->getExcelColumnLetter($columnIndex + 1);

        foreach ($this->xliffItems as $id => $localLangContent) {
            $columnIndex = 1;
            $row++;
            $sheet->setCellValueByColumnAndRow($columnIndex, $row, $id);
            foreach ($localLangContent as $languageKey => $items) {
                $columnIndex = $columns[strtolower($languageKey)];
                $sheet->setCellValueByColumnAndRow($columnIndex, $row, trim($items));
            }
        }

        foreach (range('A', $endLetter) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $exportPath = ExtensionManagementUtility::extPath($this->extensionName) . 'Resources/Private/Language/';
        $exportPath .= $this->extensionName . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($exportPath);
    }

    protected function getExcelColumnLetter(int $columnIndex): string
    {
        // Convert the column index to a base-26 number
        $base26 = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $base26 = chr($columnIndex % 26 + 65) . $base26;
            $columnIndex = (int)($columnIndex / 26);
        }
        return $base26;
    }
}
