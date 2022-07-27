<?php

declare(strict_types=1);

namespace Ayacoo\Xliff\Service\Export;

interface AbstractExportServiceInterface
{
    public function setXliffItems(array $xliffItems): void;

    public function save(): void;
}
