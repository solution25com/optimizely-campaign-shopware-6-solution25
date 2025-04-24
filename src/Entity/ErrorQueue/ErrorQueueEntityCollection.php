<?php declare(strict_types=1);

namespace OptimizelyCampaign\Entity\ErrorQueue;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(ErrorQueueEntity $entity)
 * @method void              set(string $key, ErrorQueueEntity $entity)
 * @method ErrorQueueEntity[]    getIterator()
 * @method ErrorQueueEntity[]    getElements()
 * @method ErrorQueueEntity|null get(string $key)
 * @method ErrorQueueEntity|null first()
 * @method ErrorQueueEntity|null last()
 */
class ErrorQueueEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ErrorQueueEntity::class;
    }
}