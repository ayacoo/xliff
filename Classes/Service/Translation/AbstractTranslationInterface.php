<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Translation;

interface AbstractTranslationInterface
{
    public function getTranslation(string $content, string $targetLanguage, string $sourceLanguage): array;
}
