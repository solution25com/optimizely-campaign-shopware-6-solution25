<?php declare(strict_types=1);

namespace OptimizelyCampaign\Service;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

class CustomCookieProvider implements CookieProviderInterface
{
    private const cookieGroup = [
        'snippet_name' => 'Optimizely Campaign Cookies',
        'snippet_description' => 'Store data from your cart',
        'entries' => [
            [
                'snippet_name' => 'On product',
                'cookie' => 'onProductView',
                'value' => 'true',
                'expiration' => '30',
            ],
            [
                'snippet_name' => 'On add to basket',
                'cookie' => 'onAddToBasket',
                'value' => 'true',
                'expiration' => '30',
            ],
            [
                'snippet_name' => 'On purchase',
                'cookie' => 'onPurchaseOrder',
                'value' => 'true',
                'expiration' => '30',
            ],
        ],
    ];

    private CookieProviderInterface $originalService;

    public function __construct(CookieProviderInterface $service)
    {
        $this->originalService = $service;
    }

    public function getCookieGroups(): array
    {
        return array_merge(
            $this->originalService->getCookieGroups(),
            [
                self::cookieGroup,
            ]
        );
    }
}
