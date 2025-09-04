<?php declare(strict_types=1);

namespace OptimizelyCampaign\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ProductExportTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'optimizely.productExportTask';
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
