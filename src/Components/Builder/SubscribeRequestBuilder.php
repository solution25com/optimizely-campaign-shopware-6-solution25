<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Builder;

use OptimizelyCampaign\Components\Request\AbstractOptimizelyRequest;
use OptimizelyCampaign\Components\Request\SubscribeRequest;
use OptimizelyCampaign\OptimizelyCampaign;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SubscribeRequestBuilder implements BuilderInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepository
     */
    private $errorQueueRepository;

    /**
     * @var NewsletterRecipientEntity
     */
    private $newsletterRecipient;

    /**
     * @var EntityRepository
     */
    private $newsletterRecipientRepository;

    /**
     * @var EntityRepository
     */
    private $customerRepository;

    public function __construct(
        NewsletterRecipientEntity $newsletterRecipient,
        SystemConfigService $systemConfigService,
        Context $context,
        EntityRepository $salesChannelRepository,
        EntityRepository $errorQueueRepository,
        EntityRepository $newsletterRecipientRepository,
        EntityRepository $customerRepository
    ) {
        $this->newsletterRecipient = $newsletterRecipient;
        $this->systemConfigService = $systemConfigService;
        $this->context = $context;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->errorQueueRepository = $errorQueueRepository;
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
        $this->customerRepository = $customerRepository;
    }

    public function build(): AbstractOptimizelyRequest
    {
        $salesChannel = $this->getSalesChannel($this->newsletterRecipient->getSalesChannelId(), $this->context);
        if (!($salesChannel instanceof SalesChannelEntity)) {
            throw new \Exception('Unknown sales channel: ' . $this->newsletterRecipient->getSalesChannelId());
        }

        $request = new SubscribeRequest(
            $this->errorQueueRepository,
            $salesChannel,
            $this->context,
            $this->systemConfigService,
        );

        $request->setShopId($salesChannel->getId());
        if ($salesChannel->getName() === null) {
            $request->setShop($salesChannel->getTranslated()['name']);
        } else {
            $request->setShop($salesChannel->getName());
        }

        $request->setBmOptInId($this->systemConfigService
            ->get(OptimizelyCampaign::PLUGIN_CONFIG_OPTIVO_OPT_IN_ID, $salesChannel->getId()) ?? '');

        $request->setCustomerHash($this->getNewsletterRecipientOptInId());
        $request->setEmail($this->newsletterRecipient->getEmail());
        $request->setBmOptinSource('Shopware-Integration/' . $salesChannel->getName() . '/Version:6');

        if ($this->newsletterRecipient->getSalutation()) {
            $request->setSalutation($this->newsletterRecipient->getSalutation()->getTranslation('displayName'));
        } else {
            $request->setSalutation('');
        }
        $request->setFirstname($this->newsletterRecipient->getFirstName() ?? '');
        $request->setLastname($this->newsletterRecipient->getLastName() ?? '');
        $request->setStreet($this->newsletterRecipient->getStreet() ?? '');
        $request->setZip($this->newsletterRecipient->getZipCode() ?? '');
        $request->setCity($this->newsletterRecipient->getCity() ?? '');
        $request->setPhoneNumber('');
        $request->setCompany('');
        $request->setDepartment('');
        $request->setCustomerGroup('');
        $request->setVatId('');
        $request->setCountryIso('');
        $request->setLanguage('');

        $customer = $this->findCustomerByEmail($this->newsletterRecipient->getEmail());
        if ($customer instanceof CustomerEntity) {
            if ($customer->getLanguage()) {
                if ($customer->getLanguage()->getLocale()) {
                    $request->setLanguage($customer->getLanguage()->getLocale()->getCode());
                }
            }
            if ($customer->getActiveBillingAddress()) {
                if (true) {
                    // debug start
                    $request->setStreet($customer->getActiveBillingAddress()->getStreet() ?? '');
                    $request->setZip($customer->getActiveBillingAddress()->getZipCode() ?? '');
                    $request->setCity($customer->getActiveBillingAddress()->getCity() ?? '');
                    $request->setPhoneNumber($customer->getActiveBillingAddress()->getPhoneNumber() ?? '');
                    $request->setCountryIso($customer->getActiveBillingAddress()->getCountry()->getIso());
                // debug end
                } else {
                    $request->setStreet($customer->getActiveBillingAddress()->getStreet() ?? '');
                    $request->setZip($this->newsletterRecipient->getZipCode() ?? '');
                    $request->setCity($this->newsletterRecipient->getCity() ?? '');
                    $request->setPhoneNumber($customer->getActiveBillingAddress()->getPhoneNumber() ?? '');
                    $request->setCountryIso($customer->getActiveBillingAddress()->getCountry()->getIso());
                }
            }

            $request->setCompany($customer->getCompany() ?? '');
            if ($customer->getGroup() instanceof CustomerGroupEntity) {
                $request->setCustomerGroup($customer->getGroup()->getName() ?? '');
            }
            $vatIds = $customer->getVatIds() ?? [];
            if (version_compare(\PHP_VERSION, '7.4.0') >= 0) {
                $request->setVatId(implode(';', $vatIds));
            } else {
                $request->setVatId(implode(';', $vatIds));
            }

            $request->setDepartment($customer->getActiveBillingAddress()->getDepartment() ?? '');
        }

        return $request;
    }

    private function getSalesChannel(string $salesChannelId, Context $context): ?SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);

        return $this->salesChannelRepository->search($criteria, $context)->first();
    }

    private function saveOptInId(string $optInId): void
    {
        $customFields = $this->newsletterRecipient->getCustomFields() ?? [];
        $customFields[OptimizelyCampaign::NEWSLETTER_RECIPIENT_OPTIVO_OPT_IN_ID] = $optInId;

        $this->newsletterRecipientRepository->update([
            [
                'id' => $this->newsletterRecipient->getId(),
                'customFields' => $customFields,
            ],
        ], $this->context);
    }

    private function getNewsletterRecipientOptInId()
    {
        $customFields = $this->newsletterRecipient->getCustomFields() ?? [];
        $optInId = $customFields[OptimizelyCampaign::NEWSLETTER_RECIPIENT_OPTIVO_OPT_IN_ID] ?? '';
        if (empty($optInId)) {
            $optInId = Uuid::randomHex();
            $this->saveOptInId($optInId);
        }

        return $optInId;
    }

    private function findCustomerByEmail(string $email): ?CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->addAssociation('group');
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('activeBillingAddress');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('activeBillingAddress.country');
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');

        return $this->customerRepository->search($criteria, $this->context)->first();
    }
}
