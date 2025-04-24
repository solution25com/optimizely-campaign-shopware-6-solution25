<?php declare(strict_types=1);

namespace OptimizelyCampaign\Storefront\Controller;

use OptimizelyCampaign\Service\NewsletterService;
use OptimizelyCampaign\Service\SubscriptionConfirmationException;
use OptimizelyCampaign\Service\UnsubscriptionConfirmationException;
use OptimizelyCampaign\Storefront\Page\OptimizelyCampaign\OptimizelyCampaignConfirmationPageLoader;
use Shopware\Core\Framework\Routing\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
*
 * @internal
 */
class OptimizelyCampaignController extends StorefrontController
{
    /**
     * @var OptimizelyCampaignConfirmationPageLoader
     */
    private $optimizelyPageLoader;

    /**
     * @var NewsletterService
     */
    private $newsletterService;

    public function __construct(
        OptimizelyCampaignConfirmationPageLoader $optimizelyConfirmationPageLoader,
        NewsletterService $newsletterService
    ) {
        $this->optimizelyPageLoader = $optimizelyConfirmationPageLoader;
        $this->newsletterService = $newsletterService;
    }

    /**
     * @Route("/optimizely", name="frontend.optimizely.index", options={"seo"="false"}, methods={"GET"})
     */
    public function index(Request $request, SalesChannelContext $context): Response
    {


        $optInId = $request->get('hash', '');
        $shopId = $request->get('shop-id', '');
        $shop = $request->get('shop', '');

        try {
            $this->newsletterService->confirmSubscription($optInId, $shopId, $shop, $context->getContext());

            $this->addFlash('success', $this->trans("OptimizelyCampaign.recipientSuccessfulCreated"));
        } catch (SubscriptionConfirmationException $exception) {
            $this->addFlash('warning', $this->trans($exception->getMessage()));
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->renderStorefront('@OptimizelyCampaign/storefront/page/optimizely-campaign/index.html.twig', [
            'page' => $this->optimizelyPageLoader->load($request, $context)
        ]);
    }

    /**
     * @Route("/optimizely/unsubscribe", name="frontend.optimizely.unsubscribe", options={"seo"="false"}, methods={"GET"})
     */
    public function unsubscribe(Request $request, SalesChannelContext $context): Response
    {
        $optInId = $request->get('hash', '');
        $shopId = $request->get('shop-id', '');

        try {
            $this->newsletterService->unsubscribeByOptInId($optInId, $shopId, $context->getContext());

            $this->addFlash('success', $this->trans("OptimizelyCampaign.recipientSuccessfulUnsubscribed"));
        } catch (UnsubscriptionConfirmationException $exception) {
            $this->addFlash('warning', $this->trans($exception->getMessage()));
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->renderStorefront('@OptimizelyCampaign/storefront/page/optimizely-campaign/index.html.twig', [
            'page' => $this->optimizelyPageLoader->load($request, $context)
        ]);
    }
}
