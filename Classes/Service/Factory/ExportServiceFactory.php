<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Factory;

use Ayacoo\Xliff\Service\Export\CsvExportService;
use Ayacoo\Xliff\Service\Export\JsonExportService;
use Ayacoo\Xliff\Service\Export\XlsxExportService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExportServiceFactory
{
    protected string $extensionName = '';
    protected bool $singleFileExport = false;

    public function getExtensionName(): string
    {
        return $this->extensionName;
    }

    public function setExtensionName(string $extensionName): void
    {
        $this->extensionName = $extensionName;
    }

    public function isSingleFileExport(): bool
    {
        return $this->singleFileExport;
    }

    public function setSingleFileExport(bool $singleFileExport): void
    {
        $this->singleFileExport = $singleFileExport;
    }

    public function createCsvExport(): CsvExportService
    {
        return GeneralUtility::makeInstance(CsvExportService::class, $this->getExtensionName(), $this->isSingleFileExport());
    }

    public function createXlsxExport(): XlsxExportService
    {
        return GeneralUtility::makeInstance(XlsxExportService::class, $this->getExtensionName(), $this->isSingleFileExport());
    }

    public function createJsonExport(): JsonExportService
    {
        return GeneralUtility::makeInstance(JsonExportService::class, $this->getExtensionName(), $this->isSingleFileExport());
    }
}
