<?php

namespace OptimizelyCampaign\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Storefront\Event\StorefrontRenderEvent;

class CookieRetrievalSubscriber implements EventSubscriberInterface
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onPageLoad',
        ];
    }

    public function onPageLoad(StorefrontRenderEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();

        $cookie = $request->cookies->get('optimizelyPostClick');

        if ($cookie) {
            $trackingData = json_decode($cookie, true);
            echo '<pre>' . print_r($trackingData, true) . '</pre>';
        } else {
            echo '<p>No tracking cookie found.</p>';

        }
    }
}
