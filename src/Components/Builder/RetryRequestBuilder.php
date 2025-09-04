<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Builder;

use OptimizelyCampaign\Components\Request\AbstractOptimizelyRequest;
use OptimizelyCampaign\Components\Request\RetryRequest;
use OptimizelyCampaign\Entity\ErrorQueue\ErrorQueueEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class RetryRequestBuilder implements BuilderInterface
{
    /**
     * @var ErrorQueueEntity
     */
    private $errorQueueEntry;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepository
     */
    private $errorQueueRepository;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    public function __construct(
        ErrorQueueEntity $errorQueueEntry,
        SystemConfigService $systemConfigService,
        EntityRepository $salesChannelRepository,
        EntityRepository $errorQueueRepository
    ) {
        $this->errorQueueEntry = $errorQueueEntry;
        $this->systemConfigService = $systemConfigService;
        $this->context = Context::createDefaultContext();
        $this->salesChannelRepository = $salesChannelRepository;
        $this->errorQueueRepository = $errorQueueRepository;
    }

    public function build(): AbstractOptimizelyRequest
    {
        $salesChannel = $this->errorQueueEntry->getSalesChannel();
        if ($salesChannel === null) {
            if (empty($this->errorQueueEntry->getSalesChannelId())) {
                throw new \Exception('Can not build retry request. Sales channel not set');
            }
            $salesChannel = $this->getSalesChannel($this->errorQueueEntry->getSalesChannelId());
        }

        return new RetryRequest(
            $this->errorQueueRepository,
            $salesChannel,
            $this->context,
            $this->systemConfigService,
            $this->errorQueueEntry
        );
    }

    private function getSalesChannel(string $salesChannelId): SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);

        return $this->salesChannelRepository->search($criteria, $this->context)->first();
    }
}
