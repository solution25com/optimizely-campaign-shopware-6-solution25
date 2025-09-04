<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Builder;

use OptimizelyCampaign\Components\Request\AbstractOptimizelyRequest;
use OptimizelyCampaign\Components\Request\UnsubscribeRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class UnsubscribeRequestBuilder implements BuilderInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepository
     */
    private $errorQueueRepository;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(
        string $email,
        string $salesChannelId,
        SystemConfigService $systemConfigService,
        Context $context,
        EntityRepository $salesChannelRepository,
        EntityRepository $errorQueueRepository
    ) {
        $this->email = $email;
        $this->salesChannelId = $salesChannelId;
        $this->systemConfigService = $systemConfigService;
        $this->context = $context;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->errorQueueRepository = $errorQueueRepository;
    }

    public function build(): AbstractOptimizelyRequest
    {
        $salesChannel = $this->getSalesChannel($this->salesChannelId, $this->context);
        if (!($salesChannel instanceof SalesChannelEntity)) {
            throw new \Exception('Unknown sales channel: ' . $this->salesChannelId);
        }

        $request = new UnsubscribeRequest(
            $this->errorQueueRepository,
            $salesChannel,
            $this->context,
            $this->systemConfigService,
        );

        $request->setEmail($this->email);

        return $request;
    }

    private function getSalesChannel(string $salesChannelId, Context $context): ?SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);

        return $this->salesChannelRepository->search($criteria, $context)->first();
    }
}
