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

class GenerateCommand extends Command
{
    private ?XliffService $xliffService;

    protected function configure(): void
    {
        $this->setDescription('Generate and translate xliff files for defined languages');
        $this->addOption(
            'extension',
            null,
            InputOption::VALUE_REQUIRED,
            'Name of your extension',
            ''
        );
        $this->addOption(
            'languages',
            null,
            InputOption::VALUE_REQUIRED,
            'Comma separated list of target languages',
            ''
        );
        $this->addOption(
            'translate',
            null,
            InputOption::VALUE_OPTIONAL,
            'Auto Translation via deepl (true|false)',
            false
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
        $targetLanguages = Generalutility::trimExplode(',', $input->getOption('languages'));
        $autoTranslate = (bool)$input->getOption('translate');

        $pattern = 'locallang*.xlf';
        $path = ExtensionManagementUtility::extPath($extensionName) . 'Resources/Private/Language';

        $finder = new Finder();
        $finder->files()->in($path)->name($pattern);

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $absoluteFilePath = $file->getRealPath();
                $fileNameWithExtension = $file->getRelativePathname();
                foreach ($targetLanguages as $targetLanguage) {
                    $targetFileName = $path . '/' . $targetLanguage . '.' . $fileNameWithExtension;

                    // Check if a translation already exists and use its content, if available.
                    $translatedTargets = [];
                    if (file_exists($targetFileName)) {
                        $targetXliffContent = simplexml_load_string(file_get_contents($targetFileName));
                        $targetTransUnitItems = $this->xliffService->getTransUnitElements($targetXliffContent);
                        foreach ($targetTransUnitItems as $targetTransUnitItem) {
                            $id = (string)$targetTransUnitItem->attributes()->id;
                            // CDATA Check
                            $value = (array)$targetTransUnitItem->target;
                            if (count($value) > 0) {
                                $valueString = $value[0];
                            } else {
                                $valueString = ((string)$targetTransUnitItem->target);
                            }
                            $translatedTargets[$id] = $valueString;
                        }
                    }

                    $originalXliffContent = simplexml_load_string(file_get_contents($absoluteFilePath));
                    $fileAttributes = (array)$originalXliffContent->file->attributes();
                    $originalTargetLanguage = $fileAttributes['@attributes']['target-language'] ?? '';

                    // just generate language variants for original language xliff file
                    if (empty($originalTargetLanguage)) {
                        $xmlDocument = $this->xliffService->buildXliffStructure();
                        $fileTag = $this->xliffService->buildXliffFile(
                            $xmlDocument,
                            $targetLanguage,
                            $extensionName,
                            $fileNameWithExtension
                        );
                        $this->xliffService->buildXliffHeader($fileTag, $originalXliffContent);
                        $bodyTag = $this->xliffService->buildXliffBody($fileTag);
                        $transUnitItems = $this->xliffService->getTransUnitElements($originalXliffContent);
                        foreach ($transUnitItems ?? [] as $item) {
                            $transUnitTag = $bodyTag->addChild('trans-unit');
                            foreach ($item->attributes() as $attributeKey => $attributeValue) {
                                $transUnitTag->addAttribute($attributeKey, (string) $attributeValue);
                            }

                            $id = (string)$item->attributes()->id;
                            $resName = (string)$item->attributes()->resname;
                            $transUnitTag->addAttribute('resname', $resName ?: $id);

                            $this->xliffService->addChild(
                                $item,
                                $transUnitTag,
                                $io,
                                'source',
                                $targetLanguage,
                                $autoTranslate,
                                $translatedTargets[$id] ?? ''
                            );
                        }

                        $this->xliffService->saveFile($xmlDocument, $targetFileName);
                        $io->success('The file ' . $targetFileName . ' was generated');
                    }
                }
            }
        } else {
            $io->warning('No XLIFF files were found in this extension');
        }

        return Command::SUCCESS;
    }
}
