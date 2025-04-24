<?php declare(strict_types=1);

namespace OptimizelyCampaign\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ApiRequestRetryTask extends ScheduledTask
{

    public static function getTaskName(): string
    {
        return 'optimizely.apiRequestRetryTask';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }
}