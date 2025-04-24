<?php declare(strict_types=1);

namespace OptimizelyCampaign\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerEmailChangedEvent extends Event
{
    public const EVENT_NAME = 'customer_email_changed';

    /**
     * @var CustomerEntity
     */
    private $customer;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var string
     */
    private $oldEmail;

    /**
     * @var string
     */
    private $newEmail;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        string $oldEmail,
        string $newEmail
    ) {
        $this->customer = $customer;
        $this->salesChannelContext = $salesChannelContext;
        $this->oldEmail = $oldEmail;
        $this->newEmail = $newEmail;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelContext->getSalesChannel()->getId();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class));
    }

    /**
     * @return string
     */
    public function getOldEmail(): string
    {
        return $this->oldEmail;
    }

    /**
     * @return string
     */
    public function getNewEmail(): string
    {
        return $this->newEmail;
    }
}
