<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Translation;

use GuzzleHttp\Exception\ClientException;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;

class DeeplService implements AbstractTranslationInterface
{
    private const CACHE_LIFETIME = 86400 * 30;
    private ?RequestFactory $requestFactory;
    public array $apiSupportedLanguages = [];
    public array $formalitySupportedLanguages = [];
    private array $extConf;
    private ?CacheManager $cacheManager;

    /**
     * @param RequestFactory $requestFactory
     * @param ExtensionConfiguration $extensionConfiguration
     * @param CacheManager $cacheManager
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     * @throws \JsonException
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
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
        $this->getSupportedLanguagesFromCache();
    }

    public function getApiSupportedLanguages(): array
    {
        return $this->apiSupportedLanguages;
    }

    public function getFormalitySupportedLanguages(): array
    {
        return $this->formalitySupportedLanguages;
    }

    /**
     * @param array $apiSupportedLanguages
     */
    public function setApiSupportedLanguages(array $apiSupportedLanguages): void
    {
        $this->apiSupportedLanguages = $apiSupportedLanguages;
    }

    /**
     * @param array $formalitySupportedLanguages
     */
    public function setFormalitySupportedLanguages(array $formalitySupportedLanguages): void
    {
        $this->formalitySupportedLanguages = $formalitySupportedLanguages;
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
        if (!in_array($targetLanguage, $this->getApiSupportedLanguages(), true)) {
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
        if (!empty($this->extConf['deeplFormality']) && in_array($targetLanguage, $this->getFormalitySupportedLanguages(), true)) {
            $postFields['formality'] = $this->extConf['deeplFormality'];
        }
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

    public function fetchDeeplLanguages(): void
    {
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
        } catch (ClientException $e) {

        }
    }

    /**
     * @return void
     * @throws \JsonException
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    protected function getSupportedLanguagesFromCache(): void
    {
        $supportedApiLanguagesIdentifier = 'deepl_supportedApiLanguages';
        $supportedFormalityLanguagesIdentifier = 'deepl_supportedFormalityLanguages';

        $cache = $this->cacheManager->getCache('tx_xliff_cache');
        $supportedApiLanguagesCache = $cache->get($supportedApiLanguagesIdentifier);
        $supportedFormalityLanguagesCache = $cache->get($supportedFormalityLanguagesIdentifier);

        if (!$supportedApiLanguagesCache || !$supportedFormalityLanguagesCache) {
            $this->fetchDeeplLanguages();
            $cache->set($supportedApiLanguagesIdentifier, $this->apiSupportedLanguages, [], self::CACHE_LIFETIME);
            $cache->set($supportedFormalityLanguagesIdentifier, $this->formalitySupportedLanguages, [], self::CACHE_LIFETIME);
        }

        $this->setApiSupportedLanguages($supportedApiLanguagesCache);
        $this->setFormalitySupportedLanguages($supportedFormalityLanguagesCache);
    }
}
