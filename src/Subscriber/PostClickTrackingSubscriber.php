<?php declare(strict_types=1);

namespace OptimizelyCampaign\Subscriber;

use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

class PostClickTrackingSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;

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

    public function onPageLoad(StorefrontRenderEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $response = $event->get;

        $utmSource = $request->query->get('utm_source');
        $utmMedium = $request->query->get('utm_medium');
        $utmCampaign = $request->query->get('utm_campaign');

        if ($utmSource || $utmMedium || $utmCampaign) {
            $trackingData = json_encode([
                'utm_source' => $utmSource,
                'utm_medium' => $utmMedium,
                'utm_campaign' => $utmCampaign,
                'timestamp' => time(),
            ]);

            $response->headers->setCookie(new Cookie('optimizelyPostClick', $trackingData, strtotime('+30 days')));
        }
    }
}
