<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Builder;

use OptimizelyCampaign\Components\Request\AbstractOptimizelyRequest;
use OptimizelyCampaign\Components\Request\UpdateFieldsRequest;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class UpdateFieldsRequestBuilder implements BuilderInterface
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
    private $customerRepository;

    private $logger;

    public function __construct(
        LoggerInterface $logger,
        NewsletterRecipientEntity $newsletterRecipient,
        SystemConfigService $systemConfigService,
        Context $context,
        EntityRepository $salesChannelRepository,
        EntityRepository $errorQueueRepository,
        EntityRepository $customerRepository
    ) {
        $this->newsletterRecipient = $newsletterRecipient;
        $this->systemConfigService = $systemConfigService;
        $this->context = $context;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->errorQueueRepository = $errorQueueRepository;
        $this->customerRepository = $customerRepository;

        $this->logger = $logger;
    }

    public function build(): AbstractOptimizelyRequest
    {
        $salesChannel = $this->getSalesChannel($this->newsletterRecipient->getSalesChannelId(), $this->context);
        if (!($salesChannel instanceof SalesChannelEntity)) {
            throw new \Exception('Unknown sales channel: ' . $this->newsletterRecipient->getSalesChannelId());
        }

        $request = new UpdateFieldsRequest(
            $this->errorQueueRepository,
            $salesChannel,
            $this->context,
            $this->systemConfigService,
        );

        $request->setEmail($this->newsletterRecipient->getEmail());
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
            $request->setCompany($customer->getCompany() ?? '');
            $vatIds = $customer->getVatIds() ?? [];
            if (version_compare(\PHP_VERSION, '7.4.0') >= 0) {
                $request->setVatId(implode(';', $vatIds));
            } else {
                $request->setVatId(implode(';', $vatIds));
            }
            if ($customer->getActiveBillingAddress()) {
                if (true) {
                    // debug start
                    $request->setLastname($customer->getLastName());
                    $request->setFirstname($customer->getFirstName());
                    $request->setStreet($customer->getActiveBillingAddress()->getStreet() ?? '');
                    $request->setZip($customer->getActiveBillingAddress()->getZipCode() ?? '');
                    $request->setCity($customer->getActiveBillingAddress()->getCity() ?? '');
                    $request->setPhoneNumber($customer->getActiveBillingAddress()->getPhoneNumber() ?? '');
                    $request->setCountryIso($customer->getActiveBillingAddress()->getCountry()->getIso());
                    $request->setCompany($customer->getActiveBillingAddress()->getCompany() ?? '');
                    $request->setDepartment($customer->getActiveBillingAddress()->getDepartment() ?? '');
                // debug end
                } else {
                    $request->setStreet($customer->getActiveBillingAddress()->getStreet() ?? '');
                    $request->setZip($this->newsletterRecipient->getZipCode() ?? '');
                    $request->setCity($this->newsletterRecipient->getCity() ?? '');
                    $request->setPhoneNumber($customer->getActiveBillingAddress()->getPhoneNumber() ?? '');
                    $request->setCountryIso($customer->getActiveBillingAddress()->getCountry()->getIso());
                    $request->setDepartment($customer->getActiveBillingAddress()->getDepartment() ?? '');
                }
            }

            if ($customer->getGroup() instanceof CustomerGroupEntity) {
                $request->setCustomerGroup($customer->getGroup()->getName() ?? '');
            }
        }

        return $request;
    }

    private function getSalesChannel(string $salesChannelId, Context $context): ?SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);

        return $this->salesChannelRepository->search($criteria, $context)->first();
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
