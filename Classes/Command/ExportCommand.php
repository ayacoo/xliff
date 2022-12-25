<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Command;

use Ayacoo\Xliff\Event\ModifyExportServiceEvent;
use Ayacoo\Xliff\Service\Export\AbstractExportServiceInterface;
use Ayacoo\Xliff\Service\Factory\ExportServiceFactory;
use Ayacoo\Xliff\Service\XliffService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class ExportCommand extends Command
{
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

    public function __construct(
        private readonly ?XliffService             $xliffService = null,
        private readonly ?ExportServiceFactory     $exportServiceFactory = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    )
    {
        parent::__construct();
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

        $extPath = ExtensionManagementUtility::extPath($extensionName);
        $searchFolder = $extPath . 'Resources/Private/Language';

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
            $exportService->save();
            $io->success('The export file(s) was written');
        }

        return Command::SUCCESS;
    }

    /**
     * @param string $extensionName
     * @param bool $singleFileExport
     * @param string $format
     * @return AbstractExportServiceInterface
     */
    protected function buildExportService(string $extensionName, bool $singleFileExport, string $format): AbstractExportServiceInterface
    {
        $this->exportServiceFactory->setExtensionName($extensionName);
        $this->exportServiceFactory->setSingleFileExport($singleFileExport);

        $exportService = match ($format) {
            'xlsx' => $this->exportServiceFactory->createXlsxExport(),
            default => $this->exportServiceFactory->createCsvExport(),
        };
        $modifyExportServiceEvent = $this->eventDispatcher->dispatch(
            new ModifyExportServiceEvent($exportService)
        );

        return $modifyExportServiceEvent->getExportService();
    }
}
