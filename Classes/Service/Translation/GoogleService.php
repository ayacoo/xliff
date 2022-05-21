<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Translation;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Client\ClientException;
use TYPO3\CMS\Core\Http\RequestFactory;

class GoogleService implements AbstractTranslationInterface
{
    private ?RequestFactory $requestFactory;

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
        $url    = $this->extConf['googleapiUrl'] . '?key=' . $this->extConf['googleapiKey'];
        $fields = array(
            'source' => urlencode(strtolower($sourceLanguage)),
            'target' => urlencode($targetLanguage),
            'q'      => $content,
        );

        // URL-ify the data for the POST
        $fields_string = "";
        foreach ($fields as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }

        rtrim($fields_string, '&');
        $contentLength = mb_strlen($fields_string, '8bit');
        try {
            $response = $this->requestFactory->request($url, 'POST', [
                'form_params' => $fields,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
                    'Content-Length' => $contentLength
                ],
            ]);

            $apiResult = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            $result['text'] = '';
            if (!empty($apiResult['data']['translations'][0]['translatedText'])) {
                $result['text'] = $apiResult['data']['translations'][0]['translatedText'];
            }

        } catch (ClientException $e) {
            $result['text']  = '';
        }

        return $result ?? [];
    }
}
