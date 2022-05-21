<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Translation;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Client\ClientException;
use TYPO3\CMS\Core\Http\RequestFactory;

class DeeplService implements AbstractTranslationInterface
{
    private ?RequestFactory $requestFactory;

    /**
     * Default supported languages
     * @see https://www.deepl.com/de/docs-api/translating-text/#request
     */
    protected array $apiSupportedLanguages = ['BG', 'CS', 'DA', 'DE', 'EL', 'EN', 'ES', 'ET', 'FI', 'FR', 'HU', 'IT', 'JA', 'LT', 'LV', 'NL', 'PL', 'PT', 'RO', 'RU', 'SK', 'SL', 'SV', 'ZH'];

    public array $formalitySupportedLanguages = ['DE', 'FR', 'IT', 'ES', 'NL', 'PL', 'PT-PT', 'PT-BR', 'RU'];

    private array $extConf;

    /**
     * @param RequestFactory $requestFactory
     * @param ExtensionConfiguration $extensionConfiguration
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct(RequestFactory $requestFactory, ExtensionConfiguration $extensionConfiguration)
    {
        $this->requestFactory = $requestFactory;
        $this->extConf = $extensionConfiguration->get('xliff') ?? [];
    }

    /**
     * @param string $content
     * @param string $targetLanguage
     * @param string $sourceLanguage
     * @return array
     * @throws \JsonException
     */
    public function getTranslation(string $content, string $targetLanguage, string $sourceLanguage): array
    {
        $targetLanguage = strtoupper($targetLanguage);
        // target-language isn't supported
        if (!in_array($targetLanguage, $this->apiSupportedLanguages, true)) {
            return [];
        }

        $postFieldString = '';
        $postFields = [
            'auth_key' => $this->extConf['deeplApiKey'],
            'text' => $content,
            'source_lang' => urlencode($sourceLanguage),
            'target_lang' => urlencode($targetLanguage),
            'tag_handling' => urlencode('xml'),
        ];
        if (!empty($this->extConf['deeplFormality']) && in_array($targetLanguage, $this->formalitySupportedLanguages, true)) {
            $postFields['formality'] = $this->extConf['deeplFormality'];
        }
        //url-ify the data to get content length
        foreach ($postFields as $key => $value) {
            $postFieldString .= $key . '=' . $value . '&';
        }
        rtrim($postFieldString, '&');
        $contentLength = mb_strlen($postFieldString, '8bit');

        try {
            $response = $this->requestFactory->request($this->extConf['deeplApiUrl'], 'POST', [
                'form_params' => $postFields,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Content-Length' => $contentLength,
                ],
            ]);
        } catch (ClientException $e) {
            return [];
        }
        $apiResult = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $result['text'] = '';
        if (!empty($apiResult['translations'][0]['text'])) {
            $result['text'] = $apiResult['translations'][0]['text'];
        }

        return $result ?? [];
    }
}
