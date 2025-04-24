<?php declare(strict_types=1);

namespace OptimizelyCampaign\Subscriber;

use OptimizelyCampaign\Components\OptimizelyAPI;
use OptimizelyCampaign\Components\Builder\TransactionEmailRequestBuilder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailBeforeSentSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var OptimizelyAPI
     */
    private $optimizelyAPI;

    /**
     * @var EntityRepository
     */
    private $errorQueueRepository;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        StringTemplateRenderer $templateRenderer,
        EntityRepository $salesChannelRepository,
        OptimizelyAPI $optimizelyAPI,
        EntityRepository $errorQueueRepository
    ) {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->templateRenderer = $templateRenderer;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->optimizelyAPI = $optimizelyAPI;
        $this->errorQueueRepository = $errorQueueRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            MailBeforeValidateEvent::class => 'onMailBeforeSent'
        ];
    }

    public function onMailBeforeSent(MailBeforeValidateEvent $event)
    {
        try {
            $data = $event->getData();
            $salesChannelId = $data['salesChannelId'] ?? null;
            if ($this->isPluginActive($salesChannelId)) {
                $data = $event->getData();
                $customFields = $data['customFields'] ?? [];
                $optimizelyEnabled = $customFields['optimizelyEnabled'] ?? false;
                if ($optimizelyEnabled) {
                    $recipients = $data['recipients'] ?? [];
                    $requestBuilder = new TransactionEmailRequestBuilder(
                        $event,
                        $this->errorQueueRepository,
                        $this->salesChannelRepository,
                        $this->systemConfigService,
                        $this->templateRenderer,
                        $this->logger
                    );
                    foreach ($recipients as $recipientEmail => $recipientName) {
                        $this->optimizelyAPI->request($requestBuilder->setRecipientEmail($recipientEmail)->build());
                    }

                    $event->stopPropagation();
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage() . " " . $e->getTraceAsString());
        }
    }

    protected function isPluginActive(string $salesChannelId): bool
    {
        return (bool) $this->systemConfigService
                ->get('OptimizelyCampaign.config.active', $salesChannelId) ?? false;
    }
}