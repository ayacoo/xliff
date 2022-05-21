<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Service;

use Ayacoo\Xliff\Service\Translation\AbstractTranslationInterface;
use Ayacoo\Xliff\Service\Translation\DeeplService;
use SimpleXMLElement;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class XliffService
{
    private ?AbstractTranslationInterface $translationService = null;

    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        $extConf = $extensionConfiguration->get('xliff') ?? [];
        if ($extConf['translationService'] === 'deepl') {
            $this->translationService = GeneralUtility::makeInstance(DeeplService::class);
        }
    }

    /**
     * @return SimpleXMLElementExtended
     */
    public function buildXliffStructure(): SimpleXMLElementExtended
    {
        $xmlDocument = new SimpleXMLElementExtended('<?xml version="1.0" encoding="utf-8" standalone="yes" ?><xliff />');
        $xmlDocument->addAttribute('version', '1.2');
        $xmlDocument->addAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');

        return $xmlDocument;
    }

    /**
     * @param SimpleXMLElementExtended $xmlDocument
     * @param string $targetLanguage
     * @param string $extension
     * @param string $targetFileName
     * @return SimpleXMLElementExtended
     */
    public function buildXliffFile(
        SimpleXMLElementExtended $xmlDocument,
        string $targetLanguage,
        string $extension,
        string $targetFileName
    ): SimpleXMLElementExtended
    {
        $fileTag = $xmlDocument->addChild('file');
        $fileTag->addAttribute('source-language', 'en');
        if (!empty($targetLanguage)) {
            $fileTag->addAttribute('target-language', $targetLanguage);
        }
        $fileTag->addAttribute('datatype', 'plaintext');
        $fileTag->addAttribute('date', substr(date('c'), 0, 19) . 'Z');
        $fileTag->addAttribute(
            'original',
            'EXT:' . $extension . '/Resources/Private/Language/' . $targetFileName
        );
        $fileTag->addAttribute('product-name', $extension);

        return $fileTag;
    }

    /**
     * @param SimpleXMLElementExtended $fileTag
     * @param SimpleXMLElement $xliffContent
     * @return SimpleXMLElementExtended
     */
    public function buildXliffHeader(
        SimpleXMLElementExtended $fileTag,
        SimpleXMLElement $xliffContent
    ): SimpleXMLElementExtended
    {
        $headerTag = $fileTag->addChild('header');

        $items = (array)$xliffContent->file->header;
        foreach ($items ?? [] as $key => $value) {
            $headerTag->addChild($key, $value);
        }

        return $headerTag;
    }

    /**
     * @param SimpleXMLElementExtended $fileTag
     * @return SimpleXMLElementExtended
     */
    public function buildXliffBody(SimpleXMLElementExtended $fileTag): SimpleXMLElementExtended
    {
        return $fileTag->addChild('body');
    }


    /**
     * @param SimpleXMLElement $item
     * @param SimpleXMLElementExtended $transUnitTag
     * @param SymfonyStyle $io
     * @param string $type
     * @param string $targetLanguage
     * @param bool $autoTranslate
     * @return void
     * @throws \JsonException
     */
    public function addChild(
        SimpleXMLElement         $item,
        SimpleXMLElementExtended $transUnitTag,
        SymfonyStyle             $io,
        string                   $type = 'source',
        string                   $targetLanguage = '',
        bool                     $autoTranslate = false
    ): void
    {
        if (isset($item->$type)) {
            // CDATA Check
            $value = (array)$item->$type[0];
            if (count($value) > 0) {
                $valueString = $value[0];
            } else {
                $valueString = ((string)$item->$type);
            }

            $valueString = html_entity_decode($valueString, ENT_QUOTES, 'utf-8');
            $valueString = htmlspecialchars($valueString, ENT_QUOTES, 'utf-8');
            if (count($value) > 0) {
                $transUnitTag->addChild($type, $valueString);
            } else {
                $transUnitTag->addChildWithCDATA($type, $valueString);
            }

            if ($type === 'source' && !empty($targetLanguage)) {
                if ($autoTranslate) {
                    $result = $this->translationService->getTranslation(
                        $valueString,
                        strtoupper($targetLanguage),
                        'EN'
                    );

                    if (!empty($result['text'])) {
                        $translation = $result['text'];
                        $io->info('Translation found: ' . $valueString . ' -> ' . $translation);
                        $valueString = $translation;
                    }
                }

                if (count($value) > 0) {
                    $transUnitTag->addChild('target', $valueString);
                } else {
                    $transUnitTag->addChildWithCDATA('target', $valueString);
                }
            }
        }
    }

    /**
     * @param $xmlDocument
     * @param string $targetFileName
     * @return void
     */
    public function saveFile($xmlDocument, string $targetFileName): void
    {
        $dom = dom_import_simplexml($xmlDocument)->ownerDocument;
        $dom->formatOutput = true;
        GeneralUtility::writeFile($targetFileName, $dom->saveXML());
    }
}
