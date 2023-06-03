<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Export;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class JsonExportService implements AbstractExportServiceInterface
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
            $i = 0;
            $dataRow[$i]['id'] = 'Element ID';
            $dataRow[$i]['value'] = 'Value';
            foreach ($transUnitItems ?? [] as $item) {
                $i++;
                $dataRow[$i]['id'] = (string)$item->attributes()->id;
                $dataRow[$i]['value'] = (string)$item->source;
            }
            $jsonContent = json_encode($dataRow);

            $exportFilename = str_replace('.xlf', '.json', $absoluteFilePath);
            GeneralUtility::writeFile($exportFilename, $jsonContent);
        }
    }

    protected function buildSingleFileExport(): void
    {
        $i = 0;
        $dataRow[$i]['id'] = 'Element ID';
        foreach ($this->xliffItems as $localLangContent) {
            foreach ($localLangContent as $languageKey => $items) {
                $dataRow[$i][$languageKey] = strtoupper($languageKey);
            }
        }

        foreach ($this->xliffItems as $id => $localLangContent) {
            $i++;
            $dataRow[$i]['id'] = $id;
            foreach ($localLangContent as $languageKey => $item) {
                $dataRow[$i][$languageKey] = $item;
            }
        }

        $jsonContent = json_encode($dataRow);

        $exportPath = ExtensionManagementUtility::extPath($this->extensionName) . 'Resources/Private/Language/';
        $exportPath .= $this->extensionName . '.json';
        GeneralUtility::writeFile($exportPath, $jsonContent);
    }
}
