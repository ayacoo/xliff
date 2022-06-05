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

        $searchFolder = Environment::getExtensionsPath() . '/' . $extensionName . '/Resources/Private/Language';

        $pattern = '*.xlf';
        if (!empty($file)) {
            $pattern = $file;
            $searchFolder .= '/' . $path;
        }
        $finder = new Finder();
        $finder->files()->in($searchFolder)->name($pattern);
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $absoluteFilePath = $file->getRealPath();
                $originalXliffContent = simplexml_load_string(
                    file_get_contents($absoluteFilePath),
                    null
                    , LIBXML_NOCDATA
                );
                $transUnitItems = $this->xliffService->getTransUnitElements($originalXliffContent);
                $exportService = GeneralUtility::makeInstance(CsvExportService::class);
                if ($format === 'xlsx') {
                    $exportService = GeneralUtility::makeInstance(XlsxExportService::class);
                }
                $exportService->buildExport($transUnitItems, $absoluteFilePath);

                $io->success('The export file was written');
            }
        }

        return Command::SUCCESS;
    }
}
