<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Request;

class SubscribeRequest extends AbstractOptimizelyRequest
{
    use OptimizelySubscriberTrait;

    /**
     * @var string
     */
    protected $shopId; // shop-id

    /**
     * @var string
     */
    protected $shop; // shop

    /**
     * @var string
     */
    protected $bmOptInId; // bmOptInId

    /**
     * @var string
     */
    protected $customerHash; // customer-id

    /**
     * @var string
     */
    protected $bmOptinSource; // bmOptinSource

    public function getEndpoint(): string
    {
        return 'form';
    }

    public function getMethod(): string
    {
        return 'subscribe';
    }

    public function getData(): array
    {
        return [
            'shop-id' => $this->getShopId(),
            'shop' => $this->getShop(),
            'bmOptInId' => $this->getBmOptInId(),
            'customer-id' => $this->getCustomerHash(),
            'bmRecipientId' => $this->getEmail(),
            'bmOptinSource' => $this->getBmOptinSource(),
            'salutation' => $this->getSalutation(),
            'firstname' => $this->getFirstname(),
            'lastname' => $this->getLastname(),
            'street' => $this->getStreet(),
            'zip' => $this->getZip(),
            'city' => $this->getCity(),
            'telefon' => $this->getPhoneNumber(),
            'company' => $this->getCompany(),
            'department' => $this->getDepartment(),
            'customer-group' => $this->getCustomerGroup(),
            'vatid' => $this->getVatId(),
            'country' => $this->getCountryIso(),
            'language' => $this->getLanguage(),
        ];
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }

    public function setShopId(string $shopId): void
    {
        $this->shopId = $shopId;
    }

    public function getShop(): string
    {
        return $this->shop;
    }

    public function setShop(string $shop): void
    {
        $this->shop = $shop;
    }

    public function getBmOptInId(): string
    {
        return $this->bmOptInId;
    }

    public function setBmOptInId(string $bmOptInId): void
    {
        $this->bmOptInId = $bmOptInId;
    }

    public function getCustomerHash(): string
    {
        return $this->customerHash;
    }

    public function setCustomerHash(string $customerHash): void
    {
        $this->customerHash = $customerHash;
    }

    public function getBmOptinSource(): string
    {
        return $this->bmOptinSource;
    }

    public function setBmOptinSource(string $bmOptinSource): void
    {
        $this->bmOptinSource = $bmOptinSource;
    }
}
