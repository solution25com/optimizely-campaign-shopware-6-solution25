<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Request;

class RetryRequest extends AbstractOptimizelyRequest
{
    public function getData(): array
    {
        if (!is_null($this->errorQueueEntity)) {
            return $this->errorQueueEntity->getData();
        }

        return [];
    }

    public function getEndpoint(): string
    {
        if (!is_null($this->errorQueueEntity)) {
            return $this->errorQueueEntity->getEndpoint();
        }

        return '';
    }

    public function getMethod(): string
    {
        if (!is_null($this->errorQueueEntity)) {
            return $this->errorQueueEntity->getMethod();
        }

        return '';
    }
}