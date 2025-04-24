<?php declare(strict_types=1);

namespace OptimizelyCampaign\ScheduledTask;

use OptimizelyCampaign\Service\ApiRequestRetryService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class ApiRequestRetryTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ApiRequestRetryService
     */
    private $requestRetryService;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        ApiRequestRetryService $requestRetryService
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->logger = $logger;
        $this->requestRetryService = $requestRetryService;
    }

    public static function getHandledMessages(): iterable
    {
        return [ ApiRequestRetryTask::class ];
    }

    public function run(): void
    {
        try {
            $this->requestRetryService->retry();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage().' '.$exception->getTraceAsString());
        }
    }
}