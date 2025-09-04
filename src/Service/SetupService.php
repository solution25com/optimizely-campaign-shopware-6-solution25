<?php declare(strict_types=1);

namespace OptimizelyCampaign\Service;

use Doctrine\DBAL\Connection;
use OptimizelyCampaign\OptimizelyCampaign;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SetupService
{
    public const IS_OPTIMIZELY_CAMPAIGN_PRODUCT_EXPORT = 'isOptimizelyCampaignProductExport';

    /**
     * @var EntityRepository
     */
    private $productStreamRepository;

    /**
     * @var EntityRepository
     */
    private $productExportRepository;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepository
     */
    private $systemConfigRepository;

    /**
     * @var EntityRepository
     */
    private $productStreamFilterRepository;

    /**
     * @var EntityRepository
     */
    private $snippetSetRepository;

    /**
     * @var Connection
     */
    private $dbConnection;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepository
     */
    private $mailTemplateTypeRepository;

    /**
     * @var EntityRepository
     */
    private $mailTemplateRepository;

    public function __construct(
        EntityRepository $productStreamRepository,
        EntityRepository $productStreamFilterRepository,
        EntityRepository $productExportRepository,
        EntityRepository $salesChannelRepository,
        EntityRepository $systemConfigRepository,
        EntityRepository $snippetSetRepository,
        EntityRepository $mailTemplateTypeRepository,
        EntityRepository $mailTemplateRepository,
        Connection $dbConnection,
        Context $context
    ) {
        $this->productExportRepository = $productExportRepository;
        $this->productStreamRepository = $productStreamRepository;
        $this->productStreamFilterRepository = $productStreamFilterRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->snippetSetRepository = $snippetSetRepository;
        $this->mailTemplateTypeRepository = $mailTemplateTypeRepository;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->dbConnection = $dbConnection;
        $this->context = $context;
    }

    public function install(): void
    {
        $productStream = [
            'id' => Uuid::randomHex(),
            'name' => 'Optimizely Product Stream',
            'customFields' => [
                self::IS_OPTIMIZELY_CAMPAIGN_PRODUCT_EXPORT => true,
            ],
        ];

        $this->productStreamRepository->create([$productStream], $this->context);

        $productStreamFilter = [
            'id' => Uuid::randomHex(),
            'type' => 'range',
            'field' => 'price', 'parameters' => [
                'gte' => '0',
            ],
            'productStreamId' => $productStream['id'],
        ];

        $this->productStreamFilterRepository->create([$productStreamFilter], $this->context);

        $salesChannels = $this->getStorefrontSalesChannels();
        $i = 0;
        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            if ($i > 0) {
                continue;
            }

            $fileName = 'optimizely_' . Uuid::randomHex() . '.csv';

            $optimizelySalesChannel = [
                'id' => Uuid::randomHex(),
                'name' => 'Optimizely (' . $salesChannel->getName() . ')',
                'typeId' => Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON,
                'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),

                // default selection
                'languageId' => $salesChannel->getLanguageId(),
                'snippetSetId' => $this->getSnippetSetId(),
                'currencyId' => $salesChannel->getCurrencyId(),
                'currencyVersionId' => Defaults::LIVE_VERSION,
                'paymentMethodId' => $salesChannel->getPaymentMethodId(),
                'paymentMethodVersionId' => Defaults::LIVE_VERSION,
                'shippingMethodId' => $salesChannel->getShippingMethodId(),
                'shippingMethodVersionId' => Defaults::LIVE_VERSION,
                'countryId' => $salesChannel->getCountryId(),
                'countryVersionId' => Defaults::LIVE_VERSION,
                'customerGroupId' => $salesChannel->getCustomerGroupId(),
                'navigationCategoryId' => $salesChannel->getNavigationCategoryId(),

                // available mappings
                'currencies' => [
                    ['id' => $salesChannel->getCurrencyId()],
                ],
                'languages' => [
                    ['id' => $salesChannel->getLanguageId()],
                ],
                'shippingMethods' => [
                    ['id' => $salesChannel->getShippingMethodId()],
                ],
                'paymentMethods' => [
                    ['id' => $salesChannel->getPaymentMethodId()],
                ],
                'countries' => [
                    ['id' => $salesChannel->getCountryId()],
                ],

                'customFields' => [
                    self::IS_OPTIMIZELY_CAMPAIGN_PRODUCT_EXPORT => true,
                ],
            ];

            $this->salesChannelRepository->create([$optimizelySalesChannel], $this->context);

            $productExport = [
                'id' => Uuid::randomHex(),
                'accessKey' => AccessKeyHelper::generateAccessKey('product-export'),
                'productStreamId' => $productStream['id'],
                'storefrontSalesChannelId' => $salesChannel->getId(),
                'salesChannelId' => $optimizelySalesChannel['id'],
                'salesChannelDomainId' => $salesChannel->getDomains()->first()->getId(),
                'currencyId' => $salesChannel->getCurrencyId(),
                'fileName' => $fileName,
                'encoding' => 'UTF-8',
                'fileFormat' => 'csv',
                'includeVariants' => true,
                'generateByCronjob' => true,
                'interval' => 86400,
                'headerTemplate' => $this->headerTemplate(),
                'bodyTemplate' => $this->bodyTemplate(),
                'customFields' => [
                    self::IS_OPTIMIZELY_CAMPAIGN_PRODUCT_EXPORT => true,
                ],
            ];

            $this->productExportRepository->create([$productExport], $this->context);

            ++$i;
        }

        // install email templates
        $this->addOptimizelyEmailTemplate('customer_register', $this->getCustomerRegisterConfirmationTemplate());
        $this->addOptimizelyEmailTemplate('order_confirmation_mail', $this->getOrderRegisterConfirmationTemplate());
    }

    public function uninstall(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('configurationKey', [
            OptimizelyCampaign::PLUGIN_CONFIG_ACTIVE,
            OptimizelyCampaign::PLUGIN_CONFIG_OPTIVO_OPT_IN_ID,
            OptimizelyCampaign::PLUGIN_CONFIG_OPTIVO_AUTH_CODE,
            OptimizelyCampaign::PLUGIN_CONFIG_RUN_EXPORT,
            OptimizelyCampaign::PLUGIN_CONFIG_EXPORT_NAME,
            // OptimizelyCampaign::PLUGIN_CONFIG_EXPORT_LINK,
            OptimizelyCampaign::PLUGIN_CONFIG_SFTP_USERNAME,
            OptimizelyCampaign::PLUGIN_CONFIG_SFTP_PASSWORD,
            OptimizelyCampaign::PLUGIN_CONFIG_SFTP_PRIVATE_KEY,
        ]));

        $itemsToDelete = [];
        foreach ($this->systemConfigRepository->search($criteria, $this->context)->getIds() as $id) {
            $itemsToDelete[] = ['id' => $id];
        }

        if (\count($itemsToDelete) > 0) {
            $this->systemConfigRepository->delete($itemsToDelete, $this->context);
        }

        $criteria = new Criteria();

        $itemsToDelete = [];
        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->salesChannelRepository->search($criteria, $this->context)->getEntities() as $salesChannel) {
            if (!$salesChannel->getCustomFields()) {
                continue;
            }

            $delete = (bool) ($salesChannel->getCustomFields()[self::IS_OPTIMIZELY_CAMPAIGN_PRODUCT_EXPORT] ?? false);
            if ($delete) {
                $itemsToDelete[] = [
                    'id' => $salesChannel->getId(),
                ];
            }
        }

        if (\count($itemsToDelete) > 0) {
            $this->salesChannelRepository->delete($itemsToDelete, $this->context);
        }

        $this->dbConnection->executeStatement('DROP TABLE IF EXISTS `optimizely_campaign_error_queue`');

        $this->cleanTemplateMails();
    }

    protected function getSnippetSetId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('iso', 'en-GB'));

        /** @var string|null $id */
        $id = $this->snippetSetRepository->searchIds($criteria, Context::createDefaultContext())->getIds()[0] ?? null;

        if ($id === null) {
            $criteria = (new Criteria())->setLimit(1);

            /** @var string|null $id */
            $id = $this->snippetSetRepository->searchIds($criteria, Context::createDefaultContext())->getIds()[0] ?? null;
        }

        if ($id === null) {
            throw new \InvalidArgumentException('Unable to get default SnippetSet. Please provide a valid SnippetSetId.');
        }

        return $id;
    }

    private function getStorefrontSalesChannels(): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        return $this->salesChannelRepository->search($criteria, $this->context)->getEntities();
    }

    private function headerTemplate(): string
    {
        return '"id",{#- -#}
"name",{#- -#}
"category",{#- -#}
"sw_name",{#- -#}
"sw_description",{#- -#}
"sw_price",{#- -#}
"sw_supplier",{#- -#}
"sw_link1Url",{#- -#}
"sw_image1ImageUrl",{#- -#}
"sw_deliverytime",{#- -#}
"sw_shippingcost1",{#- -#}
"sw_shippingcost2",{#- -#}
"sw_ean"{#- -#}';
    }

    private function bodyTemplate(): string
    {
        return '"{{ product.productNumber }}",{#- -#}
"{{ product.name }}",{#- -#}
"{{ product.categories.first.name }}",{#- -#}
"{{ product.name }}",{#- -#}
"{{ product.translated.description|raw|length > 300 ? product.translated.description|raw|slice(0,300) ~ \'...\' : product.translated.description|raw }}",{#- -#}
{% set price = product.calculatedPrice %}
{% if product.calculatedPrices.count > 0 %}
    {% set price = product.calculatedPrices.last %}
{% endif %}
"{{ price.unitPrice|currency ) }}",{#- -#}
"{{ product.manufacturerNumber }}",{#- -#}
"{{ seoUrl(\'frontend.detail.page\', {\'productId\': product.id}) }}",{#- -#}
"{{ product.cover.media.url }}",{#- -#}
"{%- if product.availableStock >= product.minPurchase and product.deliveryTime -%}{{ "detail.deliveryTimeAvailable"|trans({\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}{%- elseif product.availableStock < product.minPurchase and product.deliveryTime and product.restockTime -%}{{ "detail.deliveryTimeRestock"|trans({\'%count%\': product.restockTime,\'%restockTime%\': product.restockTime,\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}{%- else -%}{{ "detail.soldOut"|trans }}{#- -#}{%- endif -%}",{#- -#}
"0.00",{#- -#}
"0.00",{#- -#}
"{{ product.ean }}"{#- -#}';
    }

    private function getCustomerRegisterConfirmationTemplate(): string
    {
        return 'salutation={{ customer.salutation.translated.letterName }};
firstname={{customer.firstName}};
lastname={{ customer.lastName }};
shopname={{ salesChannel.name }};
';
    }

    private function getOrderRegisterConfirmationTemplate(): string
    {
        return '{% set billingAddress = order.addresses.get(order.billingAddressId) %}
{% set delivery = order.deliveries.first %}
{% set currencyIsoCode = order.currency.isoCode %}
bafirstname={{ billingAddress.firstName }};
balastname={{ billingAddress.lastName }};
bacompany={{ billingAddress.company }};
bastreet={{ billingAddress.street }};
bazipcode={{ billingAddress.zipcode }};
bacity={{ billingAddress.city }};
baphone={{ billingAddress.phoneNumber }};
bacountry={{ billingAddress.country.name }};
safirstname={{ delivery.shippingOrderAddress.firstName }};
salastname={{ delivery.shippingOrderAddress.lastName }};
sacompany={{ delivery.shippingOrderAddress.company }};
sastreet={{ delivery.shippingOrderAddress.street }};
sazipcode={{ delivery.shippingOrderAddress.zipcode}};
sacity={{ delivery.shippingOrderAddress.city }};
saphone={{ delivery.shippingOrderAddress.phoneNumber }};
sacountry={{ delivery.shippingOrderAddress.country.name }};
ordernumber={{ order.orderNumber }};
orderDay={{ order.orderDateTime|date }};
orderTime={{ order.orderDateTime|date(\'H:i:s\') }};
orderPositions={% for lineItem in order.lineItems %}"{% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}"; "{{ lineItem.quantity }}"; "{{ lineItem.unitPrice|currency(currencyIsoCode) }}"; "{{ lineItem.totalPrice|currency(currencyIsoCode) }}"; "{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}";{% endfor %};
shippingcosts={{ order.deliveries.first.shippingCosts.totalPrice }};
amountNet={{ order.amountNet }};
amount={{ order.amountTotal }};
';
    }

    private function addOptimizelyEmailTemplate(string $templateTechnicalName, string $templateContent): void
    {
        $templateType = $this->getTemplateTypeByTechnicalName($templateTechnicalName);
        if ($templateType instanceof MailTemplateTypeEntity) {
            $mailTemplates = $this->getMailTemplatesByTypeId($templateType->getId());
            $updates = [];
            /** @var MailTemplateEntity $mailTemplate */
            foreach ($mailTemplates as $mailTemplate) {
                $customFields = $mailTemplate->getCustomFields() ?? [];
                $customFields['optimizelyContent'] = $templateContent;

                $updates[] = [
                    'id' => $mailTemplate->getId(),
                    'customFields' => $customFields,
                ];
            }
            if (\count($updates) > 0) {
                $this->mailTemplateRepository->update($updates, $this->context);
            }
        }
    }

    private function getTemplateTypeByTechnicalName(string $templateTechnicalName): ?MailTemplateTypeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $templateTechnicalName));

        return $this->mailTemplateTypeRepository->search($criteria, $this->context)->first();
    }

    private function getMailTemplatesByTypeId(string $mailTemplateTypeId): MailTemplateCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $mailTemplateTypeId));

        return $this->mailTemplateRepository->search($criteria, $this->context)->getEntities();
    }

    private function cleanTemplateMails(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('customFields.optimizelyContent', null)]));

        $updates = [];
        /** @var MailTemplateEntity $entity */
        foreach ($this->mailTemplateRepository->search($criteria, $this->context)->getEntities() as $entity) {
            $customFields = $entity->getCustomFields() ?? [];
            $keys = ['optimizelyContent', 'optimizelyEnabled', 'optimizelyAuthcode', 'optimizelyBmMailId'];
            $update = false;
            foreach ($keys as $key) {
                if (isset($customFields[$key])) {
                    unset($customFields[$key]);
                    $update = true;
                }
            }
            if (\count($customFields) === 0) {
                $customFields = null;
            }
            if ($update) {
                $updates[] = [
                    'id' => $entity->getId(),
                    'customFields' => $customFields,
                ];
            }
        }

        if (\count($updates) > 0) {
            $this->mailTemplateRepository->update($updates, $this->context);
        }
    }
}
