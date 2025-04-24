<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Request;

class UpdateFieldsRequest extends AbstractOptimizelyRequest
{
    use OptimizelySubscriberTrait;

    public function getData(): array
    {
        return [
            'bmRecipientId' => $this->getEmail(),
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
            'language' => $this->getLanguage()
        ];
    }

    public function getEndpoint(): string
    {
        return 'form';
    }

    public function getMethod(): string
    {
        return 'updatefields';
    }
}