<?php declare(strict_types=1);

namespace OptimizelyCampaign\Core\Checkout\Customer\SalesChannel;

use OptimizelyCampaign\Event\CustomerEmailChangedEvent;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute as ChangeEmailRouteParent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ChangeEmailRoute extends ChangeEmailRouteParent
{
    /**
     * @var ChangeEmailRouteParent
     */
    private $parent;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var EntityRepository
     */
    protected $customerRepository;

    public function __construct(
        ChangeEmailRouteParent $parent,
        EntityRepository $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $dataValidator
    ) {
        parent::__construct($customerRepository, $eventDispatcher, $dataValidator);

        $this->eventDispatcher = $eventDispatcher;
        $this->customerRepository = $customerRepository;
        $this->parent = $parent;
    }

    /**
     * @param RequestDataBag $requestDataBag
     * @param SalesChannelContext $context
     * @param CustomerEntity $customer
     * @return SuccessResponse
     */
    public function change(
        RequestDataBag $requestDataBag,
        SalesChannelContext $context,
        ?CustomerEntity $customer = null
    ): SuccessResponse
    {

        $oldEmail = $context->getCustomer()->getEmail();
        $response = $this->parent->change($requestDataBag, $context, $customer);

        $freshCustomer = $this->getCustomer($context->getCustomer()->getId(), $context->getContext());
        if ($freshCustomer instanceof CustomerEntity) {
            $event = new CustomerEmailChangedEvent($context, $freshCustomer, $oldEmail, $freshCustomer->getEmail());
            $this->eventDispatcher->dispatch($event);
        }

        return $response;
    }

    private function getCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        $criteria = new Criteria([$customerId]);

        return $this->customerRepository->search($criteria, $context)->first();
    }
}