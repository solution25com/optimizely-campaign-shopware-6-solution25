<?php declare(strict_types=1);

namespace OptimizelyCampaign\Controller;

use OptimizelyCampaign\Event\ApiCustomerDeletedEvent;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompositeEntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(defaults={"_routeScope"={"api"}})
*
 * @internal
 */
class ApiCustomerController extends ApiController
{
    const ENTITY_NAME = 'customer';

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
        Serializer $serializer,
        RequestCriteriaBuilder $searchCriteriaBuilder,
        EntityProtectionValidator $entityProtectionValidator,
        AclCriteriaValidator $criteriaValidator
    ) {
        parent::__construct($definitionRegistry,
            $serializer,
            $searchCriteriaBuilder,
            $entityProtectionValidator,
            $criteriaValidator
        );
        $this->definitionRegistry = $definitionRegistry;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Route(
     *     "/api/v{version}/customer/{path}",
     *     name="api.override.customer.delete",
     *     methods={"DELETE"}
     * )
     * @param Request $request
     * @param Context $context
     *
     * @param ResponseFactoryInterface $responseFactory
     * @param string $path
     *
     * @return Response
     */
    public function deleteCustomer(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $path
    ): Response {
        $repository = $this->definitionRegistry->getRepository(self::ENTITY_NAME);
        $entity = $repository->search(new Criteria([$path]), $context)->first();
        $response = $this->delete($request, $context, $responseFactory, self::ENTITY_NAME, $path);

        if ($response->getStatusCode() == Response::HTTP_NO_CONTENT && $entity) {
            $this->eventDispatcher->dispatch(new ApiCustomerDeletedEvent($context, $entity));
        }

        return $response;
    }
}
