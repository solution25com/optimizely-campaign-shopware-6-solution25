<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Request;

trait OptimizelySubscriberTrait
{
    /**
     * @var string
     */
    protected $email; // bmRecipientId

    /**
     * @var string
     */
    protected $salutation; // salutation

    /**
     * @var string
     */
    protected $firstname; // firstname

    /**
     * @var string
     */
    protected $lastname; // lastname

    /**
     * @var string
     */
    protected $street; // street

    /**
     * @var string
     */
    protected $zip; // zip

    /**
     * @var string
     */
    protected $city; // city

    /**
     * @var string
     */
    protected $phoneNumber; // telefon

    /**
     * @var string
     */
    protected $company; // company

    /**
     * @var string
     */
    protected $department; // department

    /**
     * @var string
     */
    protected $customerGroup; // customer-group getGroup()->getName()

    /**
     * @var string
     */
    protected $vatId; // vatid

    /**
     * @var string
     */
    protected $countryIso; // country -> getIsoName

    /**
     * @var string
     */
    protected $language;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function setSalutation(string $salutation): void
    {
        $this->salutation = $salutation;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    public function getZip(): string
    {
        return $this->zip;
    }

    public function setZip(string $zip): void
    {
        $this->zip = $zip;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    public function setCompany(string $company): void
    {
        $this->company = $company;
    }

    public function getDepartment(): string
    {
        return $this->department;
    }

    public function setDepartment(string $department): void
    {
        $this->department = $department;
    }

    public function getCustomerGroup(): string
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(string $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getVatId(): string
    {
        return $this->vatId;
    }

    public function setVatId(string $vatId): void
    {
        $this->vatId = $vatId;
    }

    public function getCountryIso(): string
    {
        return $this->countryIso;
    }

    public function setCountryIso(string $countryIso): void
    {
        $this->countryIso = $countryIso;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }
}
