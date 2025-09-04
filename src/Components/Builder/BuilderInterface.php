<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Builder;

use OptimizelyCampaign\Components\Request\AbstractOptimizelyRequest;

interface BuilderInterface
{
    public function build(): AbstractOptimizelyRequest;
}
