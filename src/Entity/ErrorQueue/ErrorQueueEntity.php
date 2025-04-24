<?php declare(strict_types=1);

namespace OptimizelyCampaign\Entity\ErrorQueue;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ErrorQueueEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var int
     */
    protected $retryCount = 0;

    /**
     * @var \DateTime|null
     */
    protected $lastRetryAt;

    /**
     * @var string
     */
    protected $response;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

    /**
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * @param int $retryCount
     */
    public function setRetryCount(int $retryCount): void
    {
        $this->retryCount = $retryCount;
    }

    /**
     * @return \DateTime|null
     */
    public function getLastRetryAt(): ?\DateTime
    {
        return $this->lastRetryAt;
    }

    /**
     * @param \DateTime|null $lastRetryAt
     */
    public function setLastRetryAt(?\DateTime $lastRetryAt): void
    {
        $this->lastRetryAt = $lastRetryAt;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function getResponse(): string
    {
        return $this->response;
    }

    /**
     * @param string $response
     */
    public function setResponse(string $response): void
    {
        $this->response = $response;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    /**
     * @param string $salesChannelId
     */
    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getData(): array
    {
        $options = $this->getOptions() ?? [];

        return $options['data'] ?? [];
    }

    public function getEndpoint(): string
    {
        $options = $this->getOptions() ?? [];

        return $options['endpoint'] ?? '';
    }

    public function getMethod(): string
    {
        $options = $this->getOptions() ?? [];

        return $options['method'] ?? '';
    }

    /**
     * @return SalesChannelEntity|null
     */
    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    /**
     * @param SalesChannelEntity|null $salesChannel
     */
    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}