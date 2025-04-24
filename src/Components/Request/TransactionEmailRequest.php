<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Request;

class TransactionEmailRequest extends AbstractOptimizelyRequest
{
    /**
     * @var string
     */
    protected $email; // bmRecipientId

    /**
     * @var string
     */
    protected $bmMailingId; //bmMailingId

    /**
     * @var array
     */
    protected $templateData = []; // template parameters

    /**
     * @var string
     */
    protected $authCode;

    public function getData(): array
    {
        return array_merge([
            'bmRecipientId' => $this->getEmail(),
            'bmMailingId' => $this->getBmMailingId()
        ], $this->getTemplateData());
    }

    public function getEndpoint(): string
    {
        return 'form';
    }

    public function getMethod(): string
    {
        return 'sendtransactionmail';
    }

    public function setAuthCode(string $authCode)
    {
        $this->authCode = $authCode;
    }

    public function getAuthCode(): string
    {
        return $this->authCode;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getBmMailingId(): string
    {
        return $this->bmMailingId;
    }

    /**
     * @param string $bmMailingId
     */
    public function setBmMailingId(string $bmMailingId): void
    {
        $this->bmMailingId = $bmMailingId;
    }

    /**
     * @return array
     */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    /**
     * @param array $templateData
     */
    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }
}