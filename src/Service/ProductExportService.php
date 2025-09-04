<?php declare(strict_types=1);

namespace OptimizelyCampaign\Service;

use OptimizelyCampaign\OptimizelyCampaign;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Twig\Environment;

class ProductExportService
{
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
    private $productExportRepository;

    /**
     * @var EntityRepository
     */
    private $productStreamRepository;

    /**
     * @var EntityRepository
     */
    private $productRepository;

    /**
     * @var ProductStreamBuilder
     */
    private $productStreamBuilder;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var CachedSalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    public function __construct(
        SystemConfigService $systemConfigService,
        ProductStreamBuilder $productStreamBuilder,
        EntityRepository $salesChannelRepository,
        EntityRepository $productRepository,
        EntityRepository $productExportRepository,
        EntityRepository $productStreamRepository,
        EntityRepository $currencyRepository,
        EntityRepository $languageRepository,
        LoggerInterface $logger,
        Environment $twig,
        CachedSalesChannelContextFactory $salesChannelContextFactory
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->productRepository = $productRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->productExportRepository = $productExportRepository;
        $this->productStreamRepository = $productStreamRepository;
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
        $this->logger = $logger;
        $this->context = Context::createDefaultContext();
        $this->twig = $twig;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
    }

    public function run(): void
    {
        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->getSalesChannels() as $salesChannel) {
            $customFields = $salesChannel->getCustomFields() ?? [];
            if (\array_key_exists(SetupService::IS_OPTIMIZELY_CAMPAIGN_PRODUCT_EXPORT, $customFields)) {
                $optimizelySalesChannel = (bool) $customFields[SetupService::IS_OPTIMIZELY_CAMPAIGN_PRODUCT_EXPORT] ?? false;
                if ($optimizelySalesChannel) {
                    continue;
                }
            }

            $isPluginActive = (bool) $this->systemConfigService->get(OptimizelyCampaign::PLUGIN_CONFIG_ACTIVE, $salesChannel->getId()) ?? false;
            if (!$isPluginActive) {
                continue;
            }

            $runExport = (bool) $this->systemConfigService->get(OptimizelyCampaign::PLUGIN_CONFIG_RUN_EXPORT, $salesChannel->getId()) ?? false;
            if (!$runExport) {
                continue;
            }

            $productExportCriteria = new Criteria();
            $productExportCriteria->addFilter(new EqualsFilter('storefrontSalesChannelId', $salesChannel->getId()));
            $productExport = $this->productExportRepository->search($productExportCriteria, $this->context)->first();
            if ($productExport) {
                $productStreamId = $productExport->getProductStreamId();
                $filters = $this->productStreamBuilder->buildFilters(
                    $productStreamId,
                    $this->context
                );

                $productCriteria = new Criteria();
                $productCriteria->addFilter(...$filters);
                $productCriteria->addFilter(new EqualsAnyFilter('visibilities.salesChannel.id', [$salesChannel->getId()]));

                $products = $this->productRepository->search($productCriteria, $this->context)->getEntities();
                $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId());
                $csvContent = $this->generateCsv($products, $productExport->getHeaderTemplate(), $productExport->getBodyTemplate(), $salesChannelContext);
                if ($csvContent) {
                    // $exportLink     = $this->systemConfigService->get( OptimizelyCampaign::PLUGIN_CONFIG_EXPORT_LINK, $salesChannel->getId() ) ?? '';
                    $exportName = $this->systemConfigService->get(OptimizelyCampaign::PLUGIN_CONFIG_EXPORT_NAME, $salesChannel->getId()) ?? 'products.csv';
                    $sftpUsername = $this->systemConfigService->get(OptimizelyCampaign::PLUGIN_CONFIG_SFTP_USERNAME, $salesChannel->getId()) ?? '';
                    $sftpPassword = $this->systemConfigService->get(OptimizelyCampaign::PLUGIN_CONFIG_SFTP_PASSWORD, $salesChannel->getId()) ?? '';
                    $sftpPrivateKey = $this->systemConfigService->get(OptimizelyCampaign::PLUGIN_CONFIG_SFTP_PRIVATE_KEY, $salesChannel->getId()) ?? '';
                    if (empty($sftpUsername) || empty($sftpPrivateKey)) {
                        continue;
                    }

                    try {
                        $rsa = new RSA();
                        if (!empty($sftpPassword)) {
                            $rsa->setPassword($sftpPassword);
                        }
                        $rsa->loadKey($sftpPrivateKey);
                        $sftp = new SFTP('transfer.campaign.optimizely.com');
                        if (!$sftp->login($sftpUsername, $rsa)) {
                            continue;
                        }

                        $salesChannelName = strtolower(preg_replace('/\s+/', '_', $salesChannel->getName()));
                        $sftp->put($salesChannelName . '_' . $exportName, $csvContent);
                    } catch (\Exception $exception) {
                        $this->logger->error($exception->getMessage());
                    }
                }
            }
        }
    }

    public function generateCsv(ProductCollection $products, string $headerTemplate, string $bodyTemplate, $salesChannelContext): string
    {
        $csv = $this->twig->createTemplate($headerTemplate)->render();
        foreach ($products as $product) {
            $csv .= "\n" . $this->twig->createTemplate($bodyTemplate)->render([
                'product' => $product,
                'context' => $salesChannelContext,
            ]);
        }

        return $csv;
    }

    protected function getSalesChannels()
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        return $this->salesChannelRepository->search($criteria, $this->context)->getEntities();
    }
}
