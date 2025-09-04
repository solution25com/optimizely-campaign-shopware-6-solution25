<?php declare(strict_types=1);

namespace OptimizelyCampaign\Subscriber;

use OptimizelyCampaign\Service\NewsletterService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Content\Newsletter\NewsletterEvents;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NewsletterSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    private $oldEmail;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepository
     */
    private $newsletterRecipientRepository;

    /**
     * @var NewsletterService
     */
    private $newsletterService;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        EntityRepository $newsletterRecipientRepository,
        NewsletterService $newsletterService
    ) {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->newsletterService = $newsletterService;
    }

    public static function getSubscribedEvents()
    {
        return [
            NewsletterRegisterEvent::class => 'onNewsletterRegisterEvent',
            NewsletterConfirmEvent::class => 'onNewsletterConfirmEvent',
            NewsletterUnsubscribeEvent::class => 'onNewsletterUnsubscribe',
            NewsletterEvents::NEWSLETTER_RECIPIENT_WRITTEN_EVENT => 'onNewsletterRecipientWritten',

            PreWriteValidationEvent::class => 'triggerChangeSet',
        ];
    }

    public function triggerChangeSet(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getScope() === 'crud' || $event->getContext()->getScope() === 'user') {
            foreach ($event->getCommands() as $command) {
                if ($command->getEntityExistence()->getEntityName() !== 'newsletter_recipient') {
                    return;
                }

                if (\array_key_exists('email', $command->getPayload())) {
                    if (\array_key_exists('id', $command->getEntityExistence()->getPrimaryKey())
                        && $command->getEntityExistence()->getPrimaryKey()['id'] !== null) {
                        $getOldEmailFromRepo = $this->getOldEmailRepo($command->getEntityExistence()->getPrimaryKey()['id'], $event);

                        if ($getOldEmailFromRepo === 'null') {
                            return;
                        }
                        $this->oldEmail = $getOldEmailFromRepo;
                    }
                } else {
                    return;
                }
            }
        }
    }

    public function getOldEmailRepo(string $newsletterId, $event): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $newsletterId));
        $newsletterData = $this->newsletterRecipientRepository->search($criteria, $event->getContext())->get($newsletterId);

        if ($newsletterData === null) {
            return 'null';
        }

        return $newsletterData->getEmail();
    }

    public function onNewsletterRegisterEvent(NewsletterRegisterEvent $event): void
    {
        if ($this->isPluginActive($event->getSalesChannelId())) {
            try {
                $newsletterRecipient = $this->getNewsletterRecipient(
                    $event->getNewsletterRecipient()->getId(),
                    $event->getContext()
                );

                $this->newsletterService->sendSubscribeRequest(
                    $newsletterRecipient,
                    $event->getContext()
                );
                $event->stopPropagation();
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage() . ' ' . $exception->getTraceAsString());
            }
        }
    }

    public function onNewsletterConfirmEvent(NewsletterConfirmEvent $event): void
    {
        if ($this->isPluginActive($event->getSalesChannelId())) {
            try {
                if ($event->getNewsletterRecipient()->getStatus() === NewsletterSubscribeRoute::STATUS_DIRECT) {
                    $newsletterRecipient = $this->getNewsletterRecipient(
                        $event->getNewsletterRecipient()->getId(),
                        $event->getContext()
                    );
                    $this->newsletterService->changeStatusToWaitingForActivation(
                        $newsletterRecipient,
                        $event->getContext()
                    );
                    $this->newsletterService->sendSubscribeRequest(
                        $newsletterRecipient,
                        $event->getContext()
                    );
                    $this->newsletterService
                        ->addFlash('info', 'OptimizelyCampaign.pleaseConfirmYourRegister');
                    $event->stopPropagation();
                }
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage() . ' ' . $exception->getTraceAsString());
            }
        }
    }

    public function onNewsletterUnsubscribe(NewsletterUnsubscribeEvent $event): void
    {
        try {
            $this->newsletterService->sendUnsubscribeRequest(
                $event->getNewsletterRecipient()->getEmail(),
                $event->getNewsletterRecipient()->getSalesChannelId(),
                $event->getContext()
            );
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage() . ' ' . $exception->getTraceAsString());
        }

        try {
            $this->newsletterService->editNewsletterRecipient([
                'id' => $event->getNewsletterRecipient()->getId(),
                'confirmedAt' => null,
            ], $event->getContext());
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage() . ' ' . $exception->getTraceAsString());
        }
    }

    public function onNewsletterRecipientWritten(EntityWrittenEvent $entityWrittenEvent): void
    {
        if ($entityWrittenEvent->getEntityName() !== NewsletterRecipientDefinition::ENTITY_NAME) {
            return;
        }

        /** @var EntityWriteResult $writeResult */
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            try {
                if (!$writeResult->hasPayload('status')) {
                    if ($writeResult->getOperation() === EntityWriteResult::OPERATION_UPDATE) {
                        $newsletterRecipientId = $writeResult->getPayload()['id'] ?? '';
                        if (empty($newsletterRecipientId)) {
                            continue;
                        }
                        $newsletterRecipient = $this->getNewsletterRecipient($newsletterRecipientId, $entityWrittenEvent->getContext());

                        // Check if is Customer if customer Exists don't change Email,
                        // need to use Customers section to edit the email!
                        if ($this->oldEmail !== null && $this->oldEmail !== $newsletterRecipient->getEmail()) {
                            try {
                                $this->newsletterService->replaceNewsletterRecipientEmailSubscriber(
                                    $this->oldEmail,
                                    $newsletterRecipient->getEmail(),
                                    $entityWrittenEvent->getContext()
                                );
                            } catch (\Exception $exception) {
                                $this->logger->error($exception->getMessage() . ' ' . $exception->getTraceAsString());
                            }
                        }

                        if ($this->isPluginActive($newsletterRecipient->getSalesChannelId())) {
                            if ($newsletterRecipient->getStatus() === NewsletterSubscribeRoute::STATUS_OPT_IN) {
                                $this->newsletterService
                                    ->sendRecipientDataSynchronizationRequest(
                                        $newsletterRecipient,
                                        $entityWrittenEvent->getContext()
                                    );
                            }
                        }
                    }
                    continue;
                }

                if ($writeResult->getProperty('status') !== NewsletterSubscribeRoute::STATUS_OPT_OUT) {
                    continue;
                }

                // recipient unsubscribed from list
                $newsletterRecipientId = $writeResult->getPayload()['id'] ?? '';
                if (empty($newsletterRecipientId)) {
                    $this->logger->error('Couldn\'t unsubscribe. Id missing in payload');
                    continue;
                }

                $newsletterRecipient = $this->getNewsletterRecipient(
                    $newsletterRecipientId,
                    $entityWrittenEvent->getContext()
                );

                $this->newsletterService
                    ->sendUnsubscribeRequest(
                        $newsletterRecipient->getEmail(),
                        $newsletterRecipient->getSalesChannelId(),
                        $entityWrittenEvent->getContext()
                    );

                try {
                    $this->newsletterService->editNewsletterRecipient([
                        'id' => $newsletterRecipient->getId(),
                        'confirmedAt' => null,
                    ], $entityWrittenEvent->getContext());
                } catch (\Exception $exception) {
                    $this->logger->error($exception->getMessage() . ' ' . $exception->getTraceAsString());
                }
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage() . ' ' . $exception->getTraceAsString());
            }
        }
    }

    protected function isPluginActive(string $salesChannelId): bool
    {
        return (bool) ($this->systemConfigService->get('OptimizelyCampaign.config.active', $salesChannelId) ?? false);
    }

    protected function getNewsletterRecipient(string $newsletterRecipientId, Context $context): ?NewsletterRecipientEntity
    {
        $criteria = new Criteria([$newsletterRecipientId]);
        $criteria->addAssociation('salutation');

        return $this->newsletterRecipientRepository->search($criteria, $context)->first();
    }
}
