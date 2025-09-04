<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Request;

class RetryRequest extends AbstractOptimizelyRequest
{
    public function getData(): array
    {
        if ($this->errorQueueEntity !== null) {
            return $this->errorQueueEntity->getData();
        }

        return [];
    }

    public function getEndpoint(): string
    {
        if ($this->errorQueueEntity !== null) {
            return $this->errorQueueEntity->getEndpoint();
        }

        return '';
    }

    public function getMethod(): string
    {
        if ($this->errorQueueEntity !== null) {
            return $this->errorQueueEntity->getMethod();
        }

        return '';
    }
}
