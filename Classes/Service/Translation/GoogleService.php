<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Translation;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;

class GoogleService implements AbstractTranslationInterface
{
    private array $extConf;

    public function __construct(
        ExtensionConfiguration           $extensionConfiguration,
        private readonly ?RequestFactory $requestFactory = null
    )
    {
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
        $url = $this->extConf['googleapiUrl'] . '?key=' . $this->extConf['googleapiKey'];
        $fields = array(
            'source' => urlencode(strtolower($sourceLanguage)),
            'target' => urlencode($targetLanguage),
            'q' => $content,
        );

        // URL-ify the data for the POST
        $fields_string = "";
        foreach ($fields as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }

        $fields_string = rtrim($fields_string, '&');
        $contentLength = mb_strlen($fields_string, '8bit');
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

        return $result ?? [];
    }
}
