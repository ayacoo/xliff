<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Command;

use Ayacoo\Xliff\Service\XliffService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MigrationCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Migrate xliff files for a extension');
        $this->addOption(
            'extension',
            null,
            InputOption::VALUE_REQUIRED,
            'Name of your extension',
            ''
        );
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_OPTIONAL,
            'Overwrites the old XLIFF file',
            true
        );
        $this->addOption(
            'empty',
            null,
            InputOption::VALUE_OPTIONAL,
            'Allow handling of empty XLIFF files',
            false
        );
        $this->addOption(
            'file',
            null,
            InputOption::VALUE_REQUIRED,
            'Name of your file',
            ''
        );
        $this->addOption(
            'path',
            null,
            InputOption::VALUE_REQUIRED,
            'Path of your file',
            ''
        );
    }

    public function __construct(
        private readonly ?XliffService $xliffService = null
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
        $overwrite = (bool)$input->getOption('overwrite');
        $allowEmptyFile = (bool)$input->getOption('empty');
        $file = $input->getOption('file') ?? '';
        $path = $input->getOption('path') ?? '';

        $pattern = '*.xlf';
        $extPath = ExtensionManagementUtility::extPath($extensionName);
        $searchFolder = $extPath . 'Resources/Private/Language';

        if (!empty($file)) {
            $pattern = $file;
            $searchFolder .= '/' . $path;
        }
        $finder = new Finder();
        $finder->files()->in($searchFolder)->name($pattern);
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $absoluteFilePath = $file->getRealPath();
                $fileNameWithExtension = $file->getRelativePathname();

                $locallang = simplexml_load_string(file_get_contents($absoluteFilePath));
                $fileAttributes = (array)$locallang->file->attributes();
                $targetLanguage = $fileAttributes['@attributes']['target-language'] ?? '';

                $targetFileName = $searchFolder . '/' . $fileNameWithExtension;

                $xmlDocument = $this->xliffService->buildXliffStructure();
                $fileTag = $this->xliffService->buildXliffFile(
                    $xmlDocument,
                    $targetLanguage,
                    $extensionName,
                    $fileNameWithExtension
                );
                $this->xliffService->buildXliffHeader($fileTag, $locallang);
                $bodyTag = $this->xliffService->buildXliffBody($fileTag);


                $transUnitItems = $this->xliffService->getTransUnitElements($locallang);
                foreach ($transUnitItems ?? [] as $item) {
                    $transUnitTag = $bodyTag->addChild('trans-unit');
                    foreach ($item->attributes() as $attributeKey => $attributeValue) {
                        $transUnitTag->addAttribute($attributeKey, (string) $attributeValue);
                    }

                    $id = (string)$item->attributes()->id;
                    $resName = (string)$item->attributes()->resname;
                    $approved = (string)$item->attributes()->approved;
                    if (empty($resName)) {
                        $transUnitTag->addAttribute('resname', $id);
                    }
                    if (empty($approved)) {
                        $transUnitTag->addAttribute('approved', 'no');
                    }

                    $this->xliffService->addChild($item, $transUnitTag, $io);
                    $this->xliffService->addChild($item, $transUnitTag, $io, 'target');
                }

                $dom = dom_import_simplexml($xmlDocument)->ownerDocument;
                $dom->formatOutput = true;
                if ($allowEmptyFile || (!$allowEmptyFile && $bodyTag->count() > 0)) {
                    if (!$overwrite) {
                        $targetFileName .= '.new';
                    }
                    GeneralUtility::writeFile($targetFileName, $dom->saveXML());
                    $io->success('The ' . $targetFileName . ' file was migrated to XLIFF version 1.2');
                } else {
                    $io->warning('The ' . $targetFileName . ' file was not migrated.
                        No XML children were found or there are comments in the original XLIFF file.');
                }
            }
        } else {
            $io->warning('No XLIFF files were found in this extension');
        }

        return Command::SUCCESS;
    }
}
