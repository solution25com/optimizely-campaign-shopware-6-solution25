<?php declare(strict_types=1);

namespace OptimizelyCampaign\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Symfony\Contracts\EventDispatcher\Event;

class ApiCustomerDeletedEvent extends Event
{
    use JsonSerializableTrait;

    public const EVENT_NAME = 'api_customer.deleted';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var CustomerEntity
     */
    private $customer;

    public function __construct(Context $context, CustomerEntity $customer)
    {
        $this->context = $context;
        $this->customer = $customer;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class));
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }
}
