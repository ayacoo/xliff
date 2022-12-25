<?php
declare(strict_types=1);

namespace Ayacoo\Xliff\Event;

use Ayacoo\Xliff\Service\Export\AbstractExportServiceInterface;

final class ModifyExportServiceEvent
{
    public function __construct(
        private readonly ?AbstractExportServiceInterface $exportService = null
    )
    {
    }

    /**
     * @return AbstractExportServiceInterface|null
     */
    public function getExportService(): ?AbstractExportServiceInterface
    {
        return $this->exportService;
    }

    /**
     * @param AbstractExportServiceInterface|null $exportService
     */
    public function setExportService(?AbstractExportServiceInterface $exportService): void
    {
        $this->exportService = $exportService;
    }
}
