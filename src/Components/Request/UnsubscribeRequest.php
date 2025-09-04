<?php declare(strict_types=1);

namespace OptimizelyCampaign\Components\Request;

class UnsubscribeRequest extends AbstractOptimizelyRequest
{
    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $mailingId;

    public function getData(): array
    {
        return [
            'bmRecipientId' => $this->getEmail(),
        ];
    }

    public function getEndpoint(): string
    {
        return 'form';
    }

    public function getMethod(): string
    {
        return 'unsubscribe';
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
}
