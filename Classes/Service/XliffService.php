<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Service;

use SimpleXMLElement;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class XliffService
{
    private ?DeeplService $deeplService;

    /**
     * @param DeeplService $deeplService
     */
    public function __construct(DeeplService $deeplService)
    {

        $this->deeplService = $deeplService;
    }

    /**
     * @param string $targetLanguage
     * @param $extension
     * @param string $targetFileName
     * @return array
     */
    public function buildXliffStructure(string $targetLanguage, $extension, string $targetFileName): array
    {
        $xmlDocument = new SimpleXMLElementExtended('<?xml version="1.0" encoding="utf-8" standalone="yes" ?><xliff />');
        $xmlDocument->addAttribute('version', '1.2');
        $xmlDocument->addAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');
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
        $fileTag->addChild('header');
        $bodyTag = $fileTag->addChild('body');

        return [$xmlDocument, $bodyTag];
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

            $valueString = htmlspecialchars($valueString, ENT_QUOTES, 'utf-8');
            if (count($value) > 0) {
                $transUnitTag->addChild($type, $valueString);
            } else {
                $transUnitTag->addChildWithCDATA($type, $valueString);
            }

            if ($type === 'source' && !empty($targetLanguage)) {
                if ($autoTranslate) {
                    $result = $this->deeplService->translateRequest($valueString, strtoupper($targetLanguage), 'EN');
                    if (!empty($result['translations'][0]['text'])) {
                        $translation = $result['translations'][0]['text'];
                        $io->info('deepl Translation found: ' . $valueString . ' -> ' . $translation);
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