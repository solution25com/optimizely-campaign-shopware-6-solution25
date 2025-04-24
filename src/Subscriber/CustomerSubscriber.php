<?php declare(strict_types=1);

namespace OptimizelyCampaign\Subscriber;

use OpenApi\Tests\Fixtures\Customer;
use OptimizelyCampaign\Event\ApiCustomerDeletedEvent;
use OptimizelyCampaign\Event\CustomerEmailChangedEvent;
use OptimizelyCampaign\Service\NewsletterService;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;


class CustomerSubscriber implements EventSubscriberInterface
{

    /**
     * @var LoggerInterface
     */
    private $logger;
    private $oldEmail;

    /**
     * @var NewsletterService
     */
    private $newsletterService;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    private EntityRepository $newsletterRecipientRepository;
    private $customerRepository;


    public function __construct(
        LoggerInterface     $logger,
        NewsletterService   $newsletterService,
        SystemConfigService $systemConfigService,
        EntityRepository    $newsletterRecipientRepository,
        EntityRepository    $customerRepository

    )
    {
        $this->logger = $logger;
        $this->newsletterService = $newsletterService;
        $this->systemConfigService = $systemConfigService;
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->customerRepository = $customerRepository;

    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
            CustomerDeletedEvent::EVENT_NAME => 'onCustomerDeleted',
            ApiCustomerDeletedEvent::class => 'onApiCustomerDeleted',
            //  aCustomerEmailChangedEvent::class => 'onCustomerEmailChanged',
            PreWriteValidationEvent::class => 'triggerChangeSet',

        ];
    }

    /**
     * @param PreWriteValidationEvent $event
     * @return void
     */
    public function triggerChangeSet(PreWriteValidationEvent $event): void
    {


        if ($event->getContext()->getScope() === "crud" || $event->getContext()->getScope() === "user") {

            foreach ($event->getCommands() as $command) {

                if ($command->getEntityExistence()->getEntityName() !== "customer") {
                    return;
                }

                if (array_key_exists('email', $command->getPayload())) {

                    if (array_key_exists('id', $command->getEntityExistence()->getPrimaryKey())
                        && !is_null($command->getEntityExistence()->getPrimaryKey()['id'])) {

                        $this->oldEmail = $this->getOldEmailRepo($command->getEntityExistence()->getPrimaryKey()['id'], $event);

                    }
                } else {
                    return;
                }

            }

        }

    }

    /**
     * @param string $customerId
     * @return string|null
     */
    public function getOldEmailRepo(string $customerId, $event): ?string
    {
        if ($customerId == null) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $customerId));
        $customerData = $this->customerRepository->search($criteria, $event->getContext())->get($customerId);
        if ($customerData == null) {

            return null;
        }
        return $customerData->getEmail();
    }

    private function logCustomerUpdate(string $customerId, string $updatedFields): void
    {
        $message = sprintf("Customer %s updated with fields: %s", $customerId, $updatedFields);
        $this->logger->info($message);
    }

    public function onCustomerWritten(EntityWrittenEvent $event)
    {


        if ($event->getContext()->getScope() === "crud" || $event->getContext()->getScope() === "user") {


            if (array_key_exists('email', $event->getPayloads()[0])) {
                $newEmail = $event->getPayloads()[0]['email'];
                if ($this->oldEmail == null) {
                    return;
                }

                $getOldEmail = $this->oldEmail;

                if ($newEmail === $getOldEmail) {
                    return;
                }
                try {

                    $this->newsletterService->replaceNewsletterRecipientEmail(
                        $getOldEmail,
                        $newEmail,
                        $event->getContext()
                    );

                } catch (\Exception $exception) {
                    $this->logger->error($exception->getMessage() . " " . $exception->getTraceAsString());
                }
            }
        }

        try {
            /** @var EntityWriteResult $writeResult */
            foreach ($event->getWriteResults() as $writeResult) {
                $payload = $writeResult->getPayload();

                if (array_key_exists('id', $payload) && !array_key_exists('newsletter', $payload)) {
                    $this->newsletterService->synchronizeCustomerData($payload['id'], $event->getContext());
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage() . " " . $exception->getTraceAsString());
        }


    }

    public function onCustomerDeleted(CustomerDeletedEvent $event)
    {
        try {
            $this->newsletterService->removeNewsletterRecipientByEmail(
                $event->getCustomer()->getEmail(),
                $event->getContext()
            );
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage() . " " . $exception->getTraceAsString());
        }
    }

    public function onApiCustomerDeleted(ApiCustomerDeletedEvent $event)
    {
        try {
            $this->newsletterService->removeNewsletterRecipientByEmail(
                $event->getCustomer()->getEmail(),
                $event->getContext()
            );
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage() . " " . $exception->getTraceAsString());
        }
    }

    public function onCustomerEmailChanged(CustomerEmailChangedEvent $event)
    {

        try {
            $this->newsletterService->replaceNewsletterRecipientEmail(
                $event->getOldEmail(),
                $event->getNewEmail(),
                $event->getContext()
            );


        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage() . " " . $exception->getTraceAsString());
        }
    }

    protected function isPluginActive(string $salesChannelId): bool
    {
        return (bool)($this->systemConfigService->get('OptimizelyCampaign.config.active', $salesChannelId) ?? false);
    }
}