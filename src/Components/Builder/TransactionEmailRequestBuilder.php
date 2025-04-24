<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Builder;

use OptimizelyCampaign\Components\Request\AbstractOptimizelyRequest;
use OptimizelyCampaign\Components\Request\TransactionEmailRequest;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;

class TransactionEmailRequestBuilder implements BuilderInterface
{
    /**
     * @var MailBeforeValidateEvent
     */
    private $event;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepository
     */
    private $errorQueueRepository;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;

    /**
     * @var string
     */
    private $recipientEmail;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        MailBeforeValidateEvent $event,
        EntityRepository $errorQueueRepository,
        EntityRepository $salesChannelRepository,
        SystemConfigService $systemConfigService,
        StringTemplateRenderer $templateRenderer,
        LoggerInterface $logger
    ) {
        $this->event = $event;
        $this->errorQueueRepository = $errorQueueRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->systemConfigService = $systemConfigService;
        $this->templateRenderer = $templateRenderer;
        $this->logger = $logger;
    }

    public function setRecipientEmail(string $recipientEmail): self
    {
        $this->recipientEmail = $recipientEmail;

        return $this;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function build(): AbstractOptimizelyRequest
    {
        $data = $this->event->getData();
        $templateData = $this->event->getTemplateData();
        $customFields = $data['customFields'] ?? [];
        $salesChannelId = $data['salesChannelId'] ?? null;
        $template = $customFields['optimizelyContent'] ?? '';

        $salesChannel = null;
        if ($salesChannelId !== null && !isset($templateData['salesChannel'])) {
            /** @var SalesChannelEntity|null $salesChannel */
            $salesChannel = $this->getSalesChannel($salesChannelId, $this->event->getContext());

            if ($salesChannel === null) {
                throw new SalesChannelNotFoundException($salesChannelId);
            }

            $templateData['salesChannel'] = $salesChannel;
        }

        $content = $this->templateRenderer->render($template, $templateData, $this->event->getContext());

        $request = new TransactionEmailRequest(
            $this->errorQueueRepository,
            $salesChannel,
            $this->event->getContext(),
            $this->systemConfigService
        );

        $request->setBmMailingId($customFields['optimizelyBmMailId'] ?? '');
        $request->setAuthCode($customFields['optimizelyAuthcode'] ?? '');
        $request->setEmail($this->getRecipientEmail());
        $request->setTemplateData($this->parseOptivoTemplate($content));

        return $request;
    }

    private function getSalesChannel(string $salesChannelId, Context $context): ?SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('mailHeaderFooter');
        $criteria->getAssociation('domains')
            ->addFilter(
                new EqualsFilter('languageId', $context->getLanguageId())
            );

        return $this->salesChannelRepository->search($criteria, $context)->first();
    }

    protected function parseOptivoTemplate(string $content): array
    {
        $data = explode(';' . PHP_EOL, $content);
        $params = [];
        foreach ($data as $row) {
            $param = explode('=', $row);
            if (!empty($param) && count($param) === 2) {
                $key = str_replace(PHP_EOL, '', $param[0]);
                $params[$key] = $param[1];
            }
        }

        return $params;
    }
}