<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Translation;

use GuzzleHttp\Exception\ClientException;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;

class DeeplService implements AbstractTranslationInterface
{
    private ?RequestFactory $requestFactory;

    protected array $apiSupportedLanguages = [];

    public array $formalitySupportedLanguages = [];

    private array $extConf;

    /**
     * @param RequestFactory $requestFactory
     * @param ExtensionConfiguration $extensionConfiguration
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct(
        RequestFactory         $requestFactory,
        ExtensionConfiguration $extensionConfiguration,
        CacheManager           $cacheManager
    )
    {
        $this->requestFactory = $requestFactory;
        $this->extConf = $extensionConfiguration->get('xliff') ?? [];
        $this->cacheManager = $cacheManager;
        $this->getSupportedApiLanguages();
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

    /**
     * @return void
     * @throws \JsonException
     */
    protected function getSupportedApiLanguages(): void
    {
        $supportedApiLanguagesIdentifier = 'deepl_supportedApiLanguages';
        $supportedFormalityLanguagesIdentifier = 'deepl_supportedFormalityLanguages';

        $cache = $this->cacheManager->getCache('tx_xliff_cache');
        $supportedApiLanguagesCache = $cache->get($supportedApiLanguagesIdentifier);
        $supportedFormalityLanguagesCache = $cache->get($supportedFormalityLanguagesIdentifier);

        if (!$supportedApiLanguagesCache || !$supportedFormalityLanguagesCache) {
            $postFields = [
                'auth_key' => $this->extConf['deeplApiKey'],
                'type' => 'target',
            ];

            $postFieldString = '';
            foreach ($postFields as $key => $value) {
                $postFieldString .= $key . '=' . $value . '&';
            }
            rtrim($postFieldString, '&');
            $contentLength = mb_strlen($postFieldString, '8bit');

            try {
                $response = $this->requestFactory->request(
                    $this->extConf['deeplLanguageApiUrl'],
                    'POST',
                    [
                        'form_params' => $postFields,
                        'headers' => [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                            'Content-Length' => $contentLength,
                        ],
                    ]
                );

                $result = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                foreach ($result as $item) {
                    $this->apiSupportedLanguages[] = $item['language'];
                    if ($item['supports_formality'] === true) {
                        $this->formalitySupportedLanguages[] = $item['language'];
                    }
                }

                $cacheLifetime = 86400 * 30;
                $cache->set($supportedApiLanguagesIdentifier, $this->apiSupportedLanguages, [], $cacheLifetime);
                $cache->set($supportedFormalityLanguagesIdentifier, $this->formalitySupportedLanguages, [], $cacheLifetime);
            } catch (ClientException $e) {

            }
        } else {
            $this->apiSupportedLanguages = $supportedApiLanguagesCache;
            $this->formalitySupportedLanguages = $supportedFormalityLanguagesCache;
        }
    }
}
