<?php declare(strict_types=1);

namespace OptimizelyCampaign\Controller;

use OptimizelyCampaign\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class ApiNewsletterRecipientController extends ApiController
{
    public const ENTITY_NAME = 'newsletter_recipient';

    /**
     * @var DefinitionInstanceRegistry
     */
    protected $definitionRegistry;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        DecoderInterface $serializer,
        RequestCriteriaBuilder $searchCriteriaBuilder,
        EntityProtectionValidator $entityProtectionValidator,
        AclCriteriaValidator $criteriaValidator
    ) {
        parent::__construct(
            $definitionRegistry,
            $serializer,
            $searchCriteriaBuilder,
            $entityProtectionValidator,
            $criteriaValidator
        );
        $this->definitionRegistry = $definitionRegistry;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    #[Route(
        path: '/api/v{version}/newsletter-recipient/{path}',
        name: 'api.override.newsletter_recipient.delete',
        methods: ['DELETE']
    )]
    public function deleteNewsletterRecipient(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $path
    ): Response {
        $repository = $this->definitionRegistry->getRepository(self::ENTITY_NAME);
        $entity = $repository->search(new Criteria([$path]), $context)->first();
        $response = $this->delete($request, $context, $responseFactory, self::ENTITY_NAME, $path);

        if ($response->getStatusCode() === Response::HTTP_NO_CONTENT && $entity) {
            $this->eventDispatcher->dispatch(new NewsletterUnsubscribeEvent($context, $entity));
        }

        return $response;
    }
}
