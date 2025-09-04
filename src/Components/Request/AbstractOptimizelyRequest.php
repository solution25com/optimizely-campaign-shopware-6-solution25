<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Request;

use OptimizelyCampaign\Entity\ErrorQueue\ErrorQueueEntity;
use OptimizelyCampaign\OptimizelyCampaign;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

abstract class AbstractOptimizelyRequest
{
    /**
     * @var ErrorQueueEntity|null
     */
    protected $errorQueueEntity;

    /**
     * @var EntityRepository
     */
    protected $errorQueueRepository;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var SalesChannelEntity
     */
    protected $salesChannel;

    /**
     * @var SystemConfigService
     */
    protected $systemConfigService;

    public function __construct(
        EntityRepository $errorQueueRepository,
        SalesChannelEntity $salesChannel,
        Context $context,
        SystemConfigService $systemConfigService,
        ?ErrorQueueEntity $errorQueueEntity = null
    ) {
        $this->errorQueueRepository = $errorQueueRepository;
        $this->salesChannel = $salesChannel;
        $this->context = $context;
        $this->systemConfigService = $systemConfigService;
        $this->errorQueueEntity = $errorQueueEntity;
    }

    abstract public function getData(): array;

    abstract public function getEndpoint(): string;

    abstract public function getMethod(): string;

    public function getAuthCode(): string
    {
        return $this->systemConfigService
            ->get(OptimizelyCampaign::PLUGIN_CONFIG_OPTIVO_AUTH_CODE, $this->salesChannel->getId()) ?? '';
    }

    public function saveRequestToErrorQueue(string $response): void
    {
        if ($this->errorQueueEntity) {
            $this->errorQueueEntity->setLastRetryAt(new \DateTime('now'));
            $this->errorQueueEntity->setRetryCount($this->errorQueueEntity->getRetryCount() + 1);
            $this->errorQueueEntity->setResponse($response);
            $this->errorQueueEntity->setOptions($this->getOptions());
        } else {
            $this->errorQueueEntity = new ErrorQueueEntity();
            $this->errorQueueEntity->setResponse($response);
            $this->errorQueueEntity->setOptions($this->getOptions());
            $this->errorQueueEntity->setSalesChannelId($this->salesChannel->getId());
        }

        if (empty($this->errorQueueEntity->getCreatedAt())) {
            $this->errorQueueEntity->setId(Uuid::randomHex());
        }

        $this->errorQueueRepository->upsert([
            [
                'id' => $this->errorQueueEntity->getId(),
                'retryCount' => $this->errorQueueEntity->getRetryCount(),
                'lastRetryAt' => $this->errorQueueEntity->getLastRetryAt(),
                'response' => $this->errorQueueEntity->getResponse(),
                'salesChannel' => ['id' => $this->errorQueueEntity->getSalesChannelId()],
                'options' => $this->errorQueueEntity->getOptions(),
            ],
        ], $this->context);
    }

    public function removeFromErrorQueue(): void
    {
        if ($this->errorQueueEntity instanceof ErrorQueueEntity) {
            if ($this->errorQueueEntity->getCreatedAt()) {
                $this->errorQueueRepository->delete([
                    ['id' => $this->errorQueueEntity->getId()],
                ], $this->context);
            }
        }
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getErrorQueueEntity(): ?ErrorQueueEntity
    {
        return $this->errorQueueEntity;
    }

    public function setErrorQueueEntity(?ErrorQueueEntity $errorQueueEntity): void
    {
        $this->errorQueueEntity = $errorQueueEntity;
    }

    private function getOptions()
    {
        return [
            'endpoint' => $this->getEndpoint(),
            'method' => $this->getMethod(),
            'data' => $this->getData(),
            'sales_channel_id' => $this->salesChannel->getId(),
        ];
    }
}
