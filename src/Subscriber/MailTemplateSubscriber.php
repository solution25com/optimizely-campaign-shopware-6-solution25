<?php declare(strict_types=1);

namespace OptimizelyCampaign\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailTemplateSubscriber implements EventSubscriberInterface
{
    const OPTIVO_AUTHCODE = 'optimizelyAuthcode';
    const OPTIVO_BM_MAIL_ID = 'optimizelyBmMailId';
    const OPTIVO_ENABLED = 'optimizelyEnabled';
    const OPTIVO_CONTENT = 'optimizelyContent';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService
    ) {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;

    }

    public static function getSubscribedEvents()
    {
        return [
            MailTemplateEvents::MAIL_TEMPLATE_LOADED_EVENT => 'onMailTemplateLoaded'
        ];
    }

    /**
     * @param EntityLoadedEvent $event
     */
    public function onMailTemplateLoaded(EntityLoadedEvent $event)
    {
        try {
            /** @var MailTemplateEntity $mailTemplate */
            foreach ($event->getEntities() as $mailTemplate) {
                $customFields = $mailTemplate->getCustomFields() ?? [];
                if (!isset($customFields[self::OPTIVO_ENABLED])) {
                    $customFields[self::OPTIVO_ENABLED] = false;
                }
                if (!isset($customFields[self::OPTIVO_AUTHCODE])) {
                    $customFields[self::OPTIVO_AUTHCODE] = '';
                }
                if (!isset($customFields[self::OPTIVO_BM_MAIL_ID])) {
                    $customFields[self::OPTIVO_BM_MAIL_ID] = '';
                }
                if (!isset($customFields[self::OPTIVO_CONTENT])) {
                    $customFields[self::OPTIVO_CONTENT] = '';
                }
                $mailTemplate->setCustomFields($customFields);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage() . " " . $e->getTraceAsString());
        }
    }
}