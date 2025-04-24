<?php declare(strict_types=1);

namespace OptimizelyCampaign\Service;

use OptimizelyCampaign\Components\OptimizelyAPI;
use OptimizelyCampaign\Components\Builder\RetryRequestBuilder;
use OptimizelyCampaign\Entity\ErrorQueue\ErrorQueueEntityCollection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ApiRequestRetryService
{
    const MAX_RETRY_COUNT = 5;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityRepository
     */
    private $errorQueueRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var OptimizelyAPI
     */
    private $optimizelyAPI;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    public function __construct(
        EntityRepository $errorQueueRepository,
        OptimizelyAPI $optimizelyAPI,
        SystemConfigService $systemConfigService,
        EntityRepository $salesChannelRepository,
        LoggerInterface $logger
    ) {
        $this->errorQueueRepository = $errorQueueRepository;
        $this->context = Context::createDefaultContext();
        $this->optimizelyAPI = $optimizelyAPI;
        $this->systemConfigService = $systemConfigService;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->logger = $logger;
    }

    public function retry()
    {
        foreach ($this->findRetryableEntries() as $errorQueueEntry) {
            try {
                $this->optimizelyAPI->request(
                    (new RetryRequestBuilder(
                        $errorQueueEntry,
                        $this->systemConfigService,
                        $this->salesChannelRepository,
                        $this->errorQueueRepository))->build()
                );
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage().' '.$exception->getTraceAsString());
            }
        }
    }

    private function findRetryableEntries(): ErrorQueueEntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('retryCount', [
            RangeFilter::LT => self::MAX_RETRY_COUNT
        ]));
        $criteria->addAssociation('salesChannel');

        return $this->errorQueueRepository->search($criteria, $this->context)->getEntities();
    }
}