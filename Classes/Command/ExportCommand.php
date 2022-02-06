<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
            InputOption::VALUE_REQUIRED,
            'Name of your file',
            ''
        );
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
        $fileName = $input->getOption('file');

        $path = Environment::getExtensionsPath() . '/' . $extensionName . '/Resources/Private/Language';
        $absoluteFilePath = $path . '/' . $fileName;
        if (file_exists($absoluteFilePath)) {
            $csvLines = [];

            $dataRow['id'] = 'Element ID';
            $dataRow['value'] = 'Value';
            $csvLines[] = CsvUtility::csvValues($dataRow, ',', '"');

            $originalXliffContent = simplexml_load_string(
                file_get_contents($absoluteFilePath),
                null
                , LIBXML_NOCDATA
            );
            $items = (array)$originalXliffContent->file->body;
            $transUnitItems = array_shift($items);
            foreach ($transUnitItems ?? [] as $item) {
                $dataRow['id'] = (string)$item->attributes()->id;
                $dataRow['value'] = (string)$item->source;
                $csvLines[] = CsvUtility::csvValues($dataRow, ',', '"');
            }

            $exportFilename = str_replace('.xlf', '.csv', $absoluteFilePath);
            GeneralUtility::writeFile($exportFilename, implode(CRLF, $csvLines));

            $io->success('The csv file ' . $exportFilename . ' was written');
        } else {
            $io->warning('The file ' . $absoluteFilePath . ' was not found');
        }

        return Command::SUCCESS;
    }
}
