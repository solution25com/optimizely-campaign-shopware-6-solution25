<?php declare(strict_types=1);

namespace OptimizelyCampaign\Subscriber;

use Composer\EventDispatcher\Event;
use OptimizelyCampaign\Service\NewsletterService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerSetDefaultBillingAddressEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddressSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var NewsletterService
     */
    private $newsletterService;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        LoggerInterface $logger,
        NewsletterService $newsletterService,
        SystemConfigService $systemConfigService
    ) {
        $this->logger = $logger;
        $this->newsletterService = $newsletterService;
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents()
    {
        return [
            CustomerSetDefaultBillingAddressEvent::class => 'onDefaultBillingAddressChanged',
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'onCustomerAddressWritten'
        ];
    }

    public function onDefaultBillingAddressChanged(CustomerSetDefaultBillingAddressEvent $event)
    {
        try {
            if ($this->isPluginActive($event->getSalesChannelId())) {
                $this->newsletterService->synchronizeCustomerData(
                    $event->getCustomer()->getId(),
                    $event->getContext()
                );
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage()." ".$exception->getTraceAsString());
        }
    }

    public function onCustomerAddressWritten(EntityWrittenEvent $event)
    {
        try {
            /** @var EntityWriteResult $writeResult */
            foreach ($event->getWriteResults() as $writeResult) {
                $payload = $writeResult->getPayload();
                if (array_key_exists('customerId', $payload)) {
                    $this->newsletterService->synchronizeCustomerData($payload['customerId'], $event->getContext());
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage()." ".$exception->getTraceAsString());
        }
    }

    protected function isPluginActive(string $salesChannelId): bool
    {
        return (bool) $this->systemConfigService
                ->get('OptimizelyCampaign.config.active', $salesChannelId) ?? false;
    }
}