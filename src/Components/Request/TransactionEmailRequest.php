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
    protected $bmMailingId; // bmMailingId

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
            'bmMailingId' => $this->getBmMailingId(),
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

    public function setAuthCode(string $authCode): void
    {
        $this->authCode = $authCode;
    }

    public function getAuthCode(): string
    {
        return $this->authCode;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getBmMailingId(): string
    {
        return $this->bmMailingId;
    }

    public function setBmMailingId(string $bmMailingId): void
    {
        $this->bmMailingId = $bmMailingId;
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }
}
