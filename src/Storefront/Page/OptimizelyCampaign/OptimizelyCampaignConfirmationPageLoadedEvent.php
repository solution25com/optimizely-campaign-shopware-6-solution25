<?php declare(strict_types=1);

namespace OptimizelyCampaign\Storefront\Page\OptimizelyCampaign;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class OptimizelyCampaignConfirmationPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var OptimizelyCampaignConfirmationPage
     */
    protected $page;

    public function __construct(
        OptimizelyCampaignConfirmationPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): OptimizelyCampaignConfirmationPage
    {
        return $this->page;
    }
}
