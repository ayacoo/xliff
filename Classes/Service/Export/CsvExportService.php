<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Export;

use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CsvExportService implements AbstractExportServiceInterface
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
        $csvLines = [];
        $dataRow['id'] = 'Element ID';
        $dataRow['value'] = 'Value';
        $csvLines[] = CsvUtility::csvValues($dataRow, ',', '"');

        foreach ($this->xliffItems as $absoluteFilePath => $transUnitItems) {
            foreach ($transUnitItems ?? [] as $item) {
                $dataRow['id'] = (string)$item->attributes()->id;
                $dataRow['value'] = (string)$item->source;
                $csvLines[] = CsvUtility::csvValues($dataRow, ',', '"');
            }

            $exportFilename = str_replace('.xlf', '.csv', $absoluteFilePath);
            GeneralUtility::writeFile($exportFilename, implode(CRLF, $csvLines));
        }
    }

    protected function buildSingleFileExport(): void
    {
        $csvLines = [];
        $dataRow['id'] = 'Element ID';
        foreach ($this->xliffItems as $localLangContent) {
            foreach ($localLangContent as $languageKey => $items) {
                $dataRow[$languageKey] = strtoupper($languageKey);
            }
        }
        $csvLines[] = CsvUtility::csvValues($dataRow, ',', '"');

        foreach ($this->xliffItems as $id => $localLangContent) {
            $dataRow = array_merge([$id], $localLangContent);
            $csvLines[] = CsvUtility::csvValues($dataRow, ',', '"');
        }

        $extPath = ExtensionManagementUtility::extPath($this->extensionName);
        $exportPath = $extPath . 'Resources/Private/Language/';
        $exportPath .= $this->extensionName . '.csv';

        GeneralUtility::writeFile($exportPath, implode(CRLF, $csvLines));
    }
}
