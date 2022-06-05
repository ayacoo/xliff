<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Command;

use Ayacoo\Xliff\Service\Export\CsvExportService;
use Ayacoo\Xliff\Service\Export\XlsxExportService;
use Ayacoo\Xliff\Service\XliffService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExportCommand extends Command
{
    private ?XliffService $xliffService;

    protected function configure(): void
    {
        $this->setDescription('Export xliff file content into csv');
        $this->addOption(
            'extension',
            null,
            InputOption::VALUE_REQUIRED,
            'Name of your extension',
            ''
        );
        $this->addOption(
            'file',
            null,
            InputOption::VALUE_OPTIONAL,
            'Name of your file',
            ''
        );
        $this->addOption(
            'path',
            null,
            InputOption::VALUE_OPTIONAL,
            'Path of your file',
            ''
        );
        $this->addOption(
            'format',
            null,
            InputOption::VALUE_OPTIONAL,
            'Export format',
            'csv'
        );
        $this->addOption(
            'singleFileExport',
            null,
            InputOption::VALUE_OPTIONAL,
            'Export all xliff files into one single file',
            true
        );
    }

    /**
     * @param XliffService $xliffService
     */
    public function __construct(XliffService $xliffService)
    {
        parent::__construct();
        $this->xliffService = $xliffService;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $extensionName = $input->getOption('extension');
        $file = $input->getOption('file');
        $path = $input->getOption('path') ?? '';
        $format = $input->getOption('format');
        $singleFileExport = (bool)$input->getOption('singleFileExport');

        $searchFolder = Environment::getExtensionsPath() . '/' . $extensionName . '/Resources/Private/Language';

        $pattern = '*.xlf';
        if (!empty($file)) {
            $pattern = $file;
            $searchFolder .= '/' . $path;
        }
        $finder = new Finder();
        $finder->files()->in($searchFolder)->name($pattern);
        if ($finder->hasResults()) {
            $exportService = $this->buildExportService($extensionName, $singleFileExport, $format);

            $allTransUnitItems = [];
            foreach ($finder as $file) {
                $absoluteFilePath = $file->getRealPath();
                $originalXliffContent = simplexml_load_string(
                    file_get_contents($absoluteFilePath),
                    null
                    , LIBXML_NOCDATA
                );
                $fileAttributes = (array)$originalXliffContent->file->attributes();
                $sourceLanguage = $fileAttributes['@attributes']['source-language'] ?? '';
                $targetLanguage = $fileAttributes['@attributes']['target-language'] ?? '';

                $transUnitItems = $this->xliffService->getTransUnitElements($originalXliffContent);

                $language = $sourceLanguage;
                $translatedFile = false;
                if (!empty($targetLanguage)) {
                    $language = $targetLanguage;
                    $translatedFile = true;
                }

                if ($singleFileExport === true) {
                    foreach ($transUnitItems as $transUnitItem) {
                        $item = [];
                        $item['id'] = (string)$transUnitItem->attributes()->id;
                        $value = (string)$transUnitItem->source;
                        if ($translatedFile === true) {
                            $value = (string)$transUnitItem->target;
                        }
                        $allTransUnitItems[$item['id']][$language] = $value;
                    }
                } else {
                    $allTransUnitItems[$absoluteFilePath] = $transUnitItems;
                }
            }

            $exportService->setXliffItems($allTransUnitItems);
            $exportService->buildExport();
            $io->success('The export file(s) was written');
        }

        return Command::SUCCESS;
    }

    /**
     * @param string $extensionName
     * @param bool $singleFileExport
     * @param string $format
     * @return CsvExportService|XlsxExportService|mixed|\Psr\Log\LoggerAwareInterface|\TYPO3\CMS\Core\SingletonInterface
     */
    protected function buildExportService(string $extensionName, bool $singleFileExport, string $format): mixed
    {
        $exportService = GeneralUtility::makeInstance(CsvExportService::class, $extensionName, $singleFileExport);
        if ($format === 'xlsx') {
            $exportService = GeneralUtility::makeInstance(XlsxExportService::class, $extensionName, $singleFileExport);
        }
        return $exportService;
    }
}
