<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
            $sheet->setCellValue([1, $row], 'Element ID');
            $sheet->setCellValue([2, $row], 'Value');

            foreach ($transUnitItems ?? [] as $item) {
                $row++;
                $id = (string)$item->attributes()->id;
                $value = (string)$item->source;

                $sheet->setCellValue([1, $row], $id);
                $sheet->setCellValue([2, $row], $value);
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
        $sheet->setCellValue([1, $row], 'Element ID');

        foreach ($this->xliffItems as $localLangContent) {
            $languageKeys = array_keys($localLangContent);
            foreach ($languageKeys as $languageKey) {
                $columnIndex++;
                $sheet->setCellValue([$columnIndex, $row], $languageKey);
            }
            break;
        }

        foreach ($this->xliffItems as $id => $localLangContent) {
            $columnIndex = 1;
            $row++;
            $sheet->setCellValue([$columnIndex, $row], $id);
            foreach ($localLangContent as $items) {
                $columnIndex++;
                $sheet->setCellValue([$columnIndex, $row], $items);
            }
        }

        $extPath = ExtensionManagementUtility::extPath($this->extensionName);
        $exportPath = $extPath . 'Resources/Private/Language/';
        $exportPath .= $this->extensionName . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($exportPath);
    }
}
