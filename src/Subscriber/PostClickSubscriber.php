<?php declare(strict_types=1);


namespace OptimizelyCampaign\Subscriber;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Event\Dispatcher;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemAddedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class PostClickSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfigService;
    private RequestStack $requestStack;
    private bool $enableAddToBasket;
    private bool $enablePurchase;
    private bool $enableProductView;
    private $logger;
    private $client;
    private EntityRepository $category;


    public function __construct(
        SystemConfigService $systemConfigService,
        Client              $httpClient,
        RequestStack        $requestStack,
        EntityRepository    $category
    )
    {
        $this->systemConfigService = $systemConfigService;
        $this->httpClient = $httpClient;
        $this->requestStack = $requestStack;
        $this->category = $category;
    }


    public static function getSubscribedEvents(): array
    {
        return [
            AfterLineItemAddedEvent::class => 'onAddToBasket',
            CheckoutFinishPageLoadedEvent::class => 'onPurchase',
            ProductPageLoadedEvent::class => 'onProductView',
        ];
    }

    public function onAddToBasket(AfterLineItemAddedEvent $event): void
    {
        $base_url = $this->getBaseUrl();
        if (!$base_url || !$this->getAuth() || !$this->getClient()) {
            return;
        }

        $context = $event->getSalesChannelContext()->getContext();
        $conditionAndEmailCheck = $this->retrieveAndStoreEmail($event->getSalesChannelContext());
        $onAddToBasketCookie = $this->requestStack->getCurrentRequest()->cookies->getBoolean('onAddToBasket');
        $addToBasketConfig = $this->getAddToBasketConfigs();

        if (!$this->isEventEnabled('enableProductView') || $conditionAndEmailCheck === null || !$onAddToBasketCookie || !$addToBasketConfig) {
            return;
        }

        $lineItems = $event->getCart()->getLineItems()->getElements();
        $lastLineItem = end($lineItems);

        if ($lastLineItem === false) {
            return;
        }

        $productName = $lastLineItem->getLabel();
        $productId = $lastLineItem->getId();
        $productPrice = $lastLineItem->getPrice()->getTotalPrice();

        $categoryIds = $lastLineItem->getPayload()['categoryIds'] ?? [];
        $categoryNames = [];
        if (!empty($categoryIds)) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('id', $categoryIds));
            $categories = $this->category->search($criteria, $context)->getEntities();

            foreach ($categories as $category) {
                $categoryNames[] = $category->getTranslation('name');
            }
        }

        $basketData = [
            'gvalue1' => $productName,
            'gvalue2' => implode(',', $categoryNames),
            'gvalue3' => $productId,
            'fvalue1' => $productPrice,
            'fvalue2' => $lastLineItem->getQuantity(),
        ];

        $basketMapping = [
            'gvalue1' => 'productName',
            'gvalue2' => 'categoryNames',
            'gvalue3' => 'productId',
            'fvalue1' => 'productPrice',
            'fvalue2' => 'productQuantity',
        ];

        $configParams = explode(',', $addToBasketConfig);

        foreach ($configParams as $key => $value) {
            $configParams[$key] = trim($value);
        }

        foreach ($basketMapping as $key => $mappedKey) {
            if (!in_array($mappedKey, $configParams)) {
                unset($basketData[$key]);
            }
        }

        $defaultValues = [
            'authToken' => $this->getAuth(),
            'bi' => '0',
            'service' => 'SWAddToBasket' . $this->getClient(),
            'type' => 'userEvent',
            'recipientId' => $conditionAndEmailCheck,
        ];

        $finalValues = array_merge($defaultValues, $basketData);

        $queryString = [];
        foreach ($finalValues as $key => $value) {
            $queryString[] = urlencode($key) . '=' . urlencode((string)$value);
        }
        $finalUrl = $base_url . '?' . implode('&', $queryString);

        $this->sendRequest($finalUrl);
    }

    public function onPurchase(CheckoutFinishPageLoadedEvent $event): void
    {
        $base_url = $this->getBaseUrl();
        if (!$base_url || !$this->getAuth() || !$this->getClient()) {
            return;
        }

        $context = $event->getSalesChannelContext()->getContext();
        $conditionAndEmailCheck = $this->retrieveAndStoreEmail($event->getSalesChannelContext());
        $onPurchaseCookie = $event->getRequest()->cookies->getBoolean('onPurchaseOrder');
        $userConfig = $this->getPurchaseConfigs();

        if (!$this->isEventEnabled('enablePurchase') ||
            $conditionAndEmailCheck === null ||
            !$onPurchaseCookie || !$userConfig) {
            return;
        }

        $configParams = explode(',', $userConfig);

        foreach ($configParams as $key => $value) {
            $configParams[$key] = trim($value);
        }

        $order = $event->getPage()->getOrder();
        $lineItems = $order->getLineItems();
        $categoryIds = [];

        foreach ($lineItems as $item) {
            $productName = $item->getLabel();

            if (isset($item->getPayload()['categoryIds'])) {
                $categoryIds = $item->getPayload()['categoryIds'];
                $categoryIds = array_unique($categoryIds);

                $categoryNames = [];
                if (!empty($categoryIds)) {
                    $criteria = new Criteria();
                    $criteria->addFilter(new EqualsAnyFilter('id', $categoryIds));
                    $categories = $this->category->search($criteria, $context)->getEntities();

                    foreach ($categories as $category) {
                        $categoryNames[] = $category->getTranslation('name');
                    }
                }
            }

            $purchaseData = [
                'gvalue1' => $productName,
                'gvalue2' => implode(',', $categoryNames),
                'gvalue3' => $order->getId(),
                'fvalue1' => $order->getAmountTotal(),
                'fvalue2' => $item->getQuantity(),
            ];

            $purchaseMapping = [
                'gvalue1' => 'productName',
                'gvalue2' => 'categoryNames',
                'gvalue3' => 'orderId',
                'fvalue1' => 'totalPrice',
                'fvalue2' => 'productQuantity',
            ];

            $defaultValues = [
                'authToken' => $this->getAuth(),
                'bi' => '0',
                'service' => 'SWPurchase' . $this->getClient(),
                'type' => 'userEvent',
                'recipientId' => $conditionAndEmailCheck,
            ];

            foreach ($purchaseMapping as $key => $mappedKey) {
                if (!in_array($mappedKey, $configParams)) {
                    unset($purchaseData[$key]);
                }
            }

            $finalValues = array_merge($defaultValues, $purchaseData);

            $queryString = [];
            foreach ($finalValues as $key => $value) {
                $queryString[] = urlencode($key) . '=' . urlencode((string)$value);
            }

            $queryString = implode('&', $queryString);
            $finalUrl = $base_url . '?' . $queryString;

            $this->sendRequest($finalUrl);
        }
    }


    public function onProductView(ProductPageLoadedEvent $event): void
    {
        $base_url = $this->getBaseUrl();
        if (!$base_url || !$this->getAuth() || !$this->getClient()) {
            return;
        }

        $conditionAndEmailCheck = $this->retrieveAndStoreEmail($event->getSalesChannelContext());
        $onProductViewCookie = $event->getRequest()->cookies->getBoolean('onProductView');
        $userConfig = $this->getProductViewConfigs();

        if (!$this->isEventEnabled('enableProductView') || $conditionAndEmailCheck == null ||
            !$onProductViewCookie || !$userConfig) {
            return;
        }


        $userConfig = $this->getProductViewConfigs();
        $configParams = explode(',', $userConfig);

        foreach ($configParams as $key => $value) {
            $configParams[$key] = trim($value);
        }

        $productData = [
            'gvalue1' => $event->getPage()->getProduct()->getTranslated()['name'],
            'gvalue2' => $event->getPage()->getHeader()->getNavigation()->getActive()->getTranslated()['name'],
            'gvalue3' => $event->getPage()->getProduct()->getId(),
            'fvalue1' => $event->getPage()->getProduct()->getCurrencyPrice('')->getNet()
        ];

        $productMapping = [
            'gvalue1' => 'productName',
            'gvalue2' => 'activeNavItem',
            'gvalue3' => 'productId',
            'fvalue1' => 'netPrice',
        ];

        foreach ($productMapping as $key => $mappedKey) {
            if (!in_array($mappedKey, $configParams)) {
                unset($productData[$key]);
            }
        }

        $defaultValues = [
            'authToken' => $this->getAuth(),
            'bi' => '0',
            'service' => 'SWProductView' . $this->getClient(),
            'type' => 'userEvent',
            'recipientId' => $conditionAndEmailCheck,
        ];

        $finalValues = array_merge($defaultValues, $productData);

        $queryString = [];
        foreach ($finalValues as $key => $value) {
            if ($key === 'gvalue4') {
                $queryString[] = urlencode($key) . '=' . $value;
            } else {
                $queryString[] = urlencode($key) . '=' . urlencode((string)$value);
            }
        }

        $queryString = implode('&', $queryString);
        $finalUrl = $base_url . '?' . $queryString;
        
        $this->sendRequest($finalUrl);
    }

    public function sendRequest(string $url): void
    {
        $client = new Client();
        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                ],
            ]);
            if ($response->getStatusCode() === 200) {
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }


    private function getUserEmail(SalesChannelContext $context): ?string
    {
        $customer = $context->getCustomer();
        return $customer !== null ? $customer->getEmail() : null;
    }

    private function isEventEnabled(string $eventName): bool
    {
        return (bool)$this->systemConfigService->get('OptimizelyCampaign.config.' . $eventName);
    }

    private function getAuth()
    {
        return $this->systemConfigService->get('OptimizelyCampaign.config.optimizelyAuthToken');
    }

    private function getBaseUrl()
    {
        return $this->systemConfigService->get('OptimizelyCampaign.config.optimizelyBaseUrl');
    }

    private function getClient()
    {
        return $this->systemConfigService->get('OptimizelyCampaign.config.optimizelyClientId');
    }

    private function getProductViewConfigs()
    {
        return $this->systemConfigService->get('OptimizelyCampaign.config.productField');

    }

    private function getPurchaseConfigs()
    {
        return $this->systemConfigService->get('OptimizelyCampaign.config.purchaseField');
    }

    private function getAddToBasketConfigs()
    {
        return $this->systemConfigService->get('OptimizelyCampaign.config.addToBasketField');
    }

    private function getEmailFromCookie(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request !== null && $request->cookies !== null ? $request->cookies->get('customerEmail') : null;
    }

    private function retrieveAndStoreEmail($salesChannelContext): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        $customerEmail = $this->getUserEmail($salesChannelContext);

        if ($customerEmail) {
            $this->setEmailCookie($customerEmail);
        } else if ($request) {
            $customerEmail = $request->cookies->get('customerEmail');
        }
        return $customerEmail;
    }

    private function setEmailCookie(string $email): void
    {
        setcookie(
            'customerEmail',
            $email,
            time() + (30 * 24 * 60 * 60),
            '/',
            '',
            false,
            true
        );
    }
}