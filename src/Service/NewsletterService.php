<?php declare(strict_types=1);

namespace OptimizelyCampaign\Service;

use OptimizelyCampaign\Components\OptimizelyAPI;
use OptimizelyCampaign\Components\Builder\SubscribeRequestBuilder;
use OptimizelyCampaign\Components\Builder\UnsubscribeRequestBuilder;
use OptimizelyCampaign\Components\Builder\UpdateFieldsRequestBuilder;
use OptimizelyCampaign\Event\NewsletterUnsubscribeEvent;
use OptimizelyCampaign\OptimizelyCampaign;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NewsletterService
{
    /**
     * @var OptimizelyAPI
     */
    private $optimizelyAPI;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepository
     */
    private $newsletterRecipientRepository;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepository
     */
    private $errorQueueRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepository
     */
    private $customerRepository;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(
        OptimizelyAPI            $optimizelyAPI,
        SystemConfigService      $systemConfigService,
        EntityRepository         $newsletterRecipientRepository,
        EntityRepository         $salesChannelRepository,
        EntityRepository         $errorQueueRepository,
        EntityRepository         $customerRepository,
        LoggerInterface          $logger,
        ContainerInterface       $container,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->optimizelyAPI = $optimizelyAPI;
        $this->systemConfigService = $systemConfigService;
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->errorQueueRepository = $errorQueueRepository;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function sendSubscribeRequest(NewsletterRecipientEntity $newsletterRecipient, Context $context)
    {
        $requestBuilder = new SubscribeRequestBuilder(
            $newsletterRecipient,
            $this->systemConfigService,
            $context,
            $this->salesChannelRepository,
            $this->errorQueueRepository,
            $this->newsletterRecipientRepository,
            $this->customerRepository
        );
        $this->optimizelyAPI->request($requestBuilder->build());
    }

    public function changeStatusToWaitingForActivation(
        NewsletterRecipientEntity $newsletterRecipient,
        Context                   $context
    )
    {
        $this->changeRecipientStatus(
            $newsletterRecipient->getId(),
            NewsletterSubscribeRoute::STATUS_NOT_SET,
            $context
        );
    }

    public function unsubscribeByOptInId(string $optInId, string $salesChannelId, Context $context)
    {
        $newsletterRecipient = $this->findNewsletterRecipient($optInId, $salesChannelId, $context);
        if (!($newsletterRecipient instanceof NewsletterRecipientEntity)) {
            throw new UnsubscriptionConfirmationException("OptimizelyCampaign.recipientWrongData");
        }

        $this->changeRecipientStatus(
            $newsletterRecipient->getId(),
            NewsletterSubscribeRoute::STATUS_OPT_OUT,
            $context
        );

        $this->sendUnsubscribeRequest($newsletterRecipient->getEmail(), $salesChannelId, $context);
    }

    public function sendUnsubscribeRequest(string $email, string $salesChannelId, Context $context)
    {
        $requestBuilder = new UnsubscribeRequestBuilder(
            $email,
            $salesChannelId,
            $this->systemConfigService,
            $context,
            $this->salesChannelRepository,
            $this->errorQueueRepository
        );
        $this->optimizelyAPI->request($requestBuilder->build());
    }

    public function sendRecipientDataSynchronizationRequest(NewsletterRecipientEntity $newsletterRecipient, Context $context)
    {
        $requestBuilder = new UpdateFieldsRequestBuilder(
            $this->logger,
            $newsletterRecipient,
            $this->systemConfigService,
            $context,
            $this->salesChannelRepository,
            $this->errorQueueRepository,
            $this->customerRepository
        );
        $this->optimizelyAPI->request($requestBuilder->build());
    }

    public function synchronizeCustomerData(string $customerId, Context $context)
    {

        //$customer = $this->getCustomer($customerId, $context);
        $customer = $this->getCustomerWithBillingAddress($customerId, $context);
        if ($customer instanceof CustomerEntity) {
            $newsletterRecipient = $this->getNewsletterRecipientByEmail($customer->getEmail(), $context);
            if ($newsletterRecipient instanceof NewsletterRecipientEntity) {
                $this->updateRecipientData($newsletterRecipient, $customer, $context);
                $this->sendRecipientDataSynchronizationRequest($newsletterRecipient, $context);
            }
        }
    }

    public function removeNewsletterRecipientByEmail(string $email, Context $context)
    {
        $newsletterRecipient = $this->getNewsletterRecipientByEmail($email, $context);
        if ($newsletterRecipient instanceof NewsletterRecipientEntity) {
            $this->removeNewsletterRecipient($newsletterRecipient, $context);
        }
    }

    public function replaceNewsletterRecipientEmail(string $oldEmail, string $newEmail, Context $context)
    {

        $oldNewsletterRecipient = $this->getNewsletterRecipientByEmail($oldEmail, $context);
        $newNewsletterRecipient = $this->getNewsletterRecipientByEmail($newEmail, $context);

        if ($oldNewsletterRecipient instanceof NewsletterRecipientEntity) {
            if ($newNewsletterRecipient instanceof NewsletterRecipientEntity) {
                $this->removeNewsletterRecipient($oldNewsletterRecipient, $context);
            } else {

                $this->sendUnsubscribeRequest(
                    $oldNewsletterRecipient->getEmail(),
                    $oldNewsletterRecipient->getSalesChannelId(),
                    $context
                );
                $oldNewsletterRecipient->setEmail($newEmail);
                $this->newsletterRecipientRepository->update([
                    [
                        'id' => $oldNewsletterRecipient->getId(),
                        'email' => $oldNewsletterRecipient->getEmail(),
                        'status' => NewsletterSubscribeRoute::STATUS_NOT_SET
                    ]
                ], $context);

                $this->sendSubscribeRequest($oldNewsletterRecipient, $context);
            }
        } elseif ($newNewsletterRecipient instanceof NewsletterRecipientEntity) {
            $customer = $this->getCustomerByEmail($newEmail, $context);
            if ($customer instanceof CustomerEntity) {
                $this->customerRepository->update([
                    [
                        'id' => $customer->getId(),
                        'newsletter' => true
                    ]
                ], $context);
            }
        }
    }


    public function replaceNewsletterRecipientEmailSubscriber(string $oldEmail, string $newEmail, Context $context)
    {

        $customer = $this->getCustomerByEmail($oldEmail, $context);
        //this fucntion is used only if there is no Customer.

        if ($customer !== null) {
            return;
        }
        //Change Email of Subscriber!
        $newNewsletterRecipient = $this->getNewsletterRecipientByEmail($newEmail, $context);

        if ($newNewsletterRecipient !== null) {

            $this->sendUnsubscribeRequest(
                $oldEmail,
                $newNewsletterRecipient->getSalesChannelId(),
                $context
            );
            $this->newsletterRecipientRepository->update([
                [
                    'id' => $newNewsletterRecipient->getId(),
                    'email' => $newNewsletterRecipient->getEmail(),
                    'status' => NewsletterSubscribeRoute::STATUS_NOT_SET
                ]
            ], $context);
            $this->sendSubscribeRequest($newNewsletterRecipient, $context);

        }

    }

    public function confirmSubscription(string $optInId, string $shopId, string $shop, Context $context)
    {
        if (empty($optInId)) {
            throw new SubscriptionConfirmationException("OptimizelyCampaign.recipientWrongData");
        }
        if (empty($shopId) && empty($shop)) {
            throw new SubscriptionConfirmationException("OptimizelyCampaign.recipientWrongData");
        }

        if (empty($shopId)) {
            $shopId = $this->findSalesChannelIdByName($shop, $context);
            if (empty($shopId)) {
                throw new SubscriptionConfirmationException("OptimizelyCampaign.recipientWrongData");
            }
        }

        $newsletterRecipient = $this->findNewsletterRecipient($optInId, $shopId, $context);
        if (!($newsletterRecipient instanceof NewsletterRecipientEntity)) {
            throw new SubscriptionConfirmationException("OptimizelyCampaign.recipientWrongData");
        }

        if ($newsletterRecipient->getStatus() === NewsletterSubscribeRoute::STATUS_OPT_IN) {
            throw new SubscriptionConfirmationException("OptimizelyCampaign.recipientAlreadyRegistered");
        }

        $this->changeRecipientStatus(
            $newsletterRecipient->getId(),
            NewsletterSubscribeRoute::STATUS_OPT_IN,
            $context
        );
    }

    public function addFlash(string $type, string $snippet, array $parameters = [])
    {
        if (!$this->container->has('session')) {
            return;
        }

        $message = $this->trans($snippet, $parameters);

        $this->container
            ->get('session')
            ->getFlashBag()
            ->add($type, $message);
    }

    public function editNewsletterRecipient(array $data, Context $context): void
    {
        $this->newsletterRecipientRepository->update([$data], $context);
    }

    protected function findNewsletterRecipient(
        string  $optInId,
        string  $salesChannelId,
        Context $context
    ): ?NewsletterRecipientEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customFields.' . OptimizelyCampaign::NEWSLETTER_RECIPIENT_OPTIVO_OPT_IN_ID, $optInId)
        );
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        return $this->newsletterRecipientRepository->search($criteria, $context)->first();
    }

    protected function findSalesChannelIdByName(string $salesChannelName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter("name", $salesChannelName));

        return $this->salesChannelRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * This function is called if the Customer data have been changed, to sync chnages in NewsletterRecipientEntity
     * @param NewsletterRecipientEntity $newsletterRecipientEntity
     * @param CustomerEntity $customerEntity
     * @param Context $context
     * @return void
     */
    private function updateRecipientData(NewsletterRecipientEntity $newsletterRecipientEntity, CustomerEntity $customerEntity, Context $context): void
    {
        $data = [
            'id' => $newsletterRecipientEntity->getId(),
            'lastName' => $customerEntity->getLastName() ?? '',
            'firstName' => $customerEntity->getFirstName() ?? '',
            'zipCode' => $customerEntity->getDefaultBillingAddress()->getZipcode() ?? '',
            'city' => $customerEntity->getDefaultBillingAddress()->getCity() ?? '',
            'street' => $customerEntity->getDefaultBillingAddress()->getStreet() ?? ''
        ];

        $this->newsletterRecipientRepository->update([$data], $context);
    }

    private function changeRecipientStatus(
        string  $newsletterRecipientId,
        string  $newStatus,
        Context $context
    )
    {
        $data = [
            'id' => $newsletterRecipientId,
            'status' => $newStatus
        ];

        if ($newStatus === NewsletterSubscribeRoute::STATUS_OPT_IN) {
            $data['confirmedAt'] = new \DateTime('now');
        }

        $this->newsletterRecipientRepository->update([$data], $context);
    }

    protected function getNewsletterRecipient(
        string  $newsletterRecipientId,
        Context $context
    ): ?NewsletterRecipientEntity
    {
        $criteria = new Criteria([$newsletterRecipientId]);

        return $this->newsletterRecipientRepository->search($criteria, $context)->first();
    }

    protected function getNewsletterRecipientByEmail(string $email, Context $context): ?NewsletterRecipientEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->addAssociation('salutation');

        return $this->newsletterRecipientRepository->search($criteria, $context)->first();
    }

    /**
     * Returns the CustomerEntity with ActiveBillingAddress to update the NewsletterRecipientEntity
     * @param string $customerId
     * @param Context $context
     * @return CustomerEntity|null
     */
    protected function getCustomerWithBillingAddress(string $customerId, Context $context): ?CustomerEntity
    {
        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('defaultBillingAddress');

        return $this->customerRepository->search($criteria, $context)->first();
    }

    protected function getCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        $criteria = new Criteria([$customerId]);

        return $this->customerRepository->search($criteria, $context)->first();
    }

    protected function getCustomerByEmail(string $email, Context $context): ?CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        return $this->customerRepository->search($criteria, $context)->first();
    }

    protected function trans(string $snippet, array $parameters = []): string
    {
        return $this->container
            ->get('translator')
            ->trans($snippet, $parameters);
    }

    protected function removeNewsletterRecipient(
        NewsletterRecipientEntity $newsletterRecipient,
        Context                   $context
    )
    {
        $this->newsletterRecipientRepository->delete([
            ['id' => $newsletterRecipient->getId()]
        ], $context);

        $this->eventDispatcher->dispatch(new NewsletterUnsubscribeEvent($context, $newsletterRecipient));
    }
}