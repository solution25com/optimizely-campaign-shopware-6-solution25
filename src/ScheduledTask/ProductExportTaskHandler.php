<?php declare(strict_types=1);

namespace OptimizelyCampaign\ScheduledTask;

use OptimizelyCampaign\Service\ProductExportService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class ProductExportTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ProductExportService
     */
    private $productExportService;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        ProductExportService $productExportService
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->logger = $logger;
        $this->productExportService = $productExportService;
    }

    public static function getHandledMessages(): iterable
    {
        return [ProductExportTask::class];
    }

    public function run(): void
    {
        try {
            $this->productExportService->run();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage() . ' ' . $exception->getTraceAsString());
        }
    }
}
