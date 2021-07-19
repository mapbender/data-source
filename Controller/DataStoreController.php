<?php
namespace Mapbender\DataSourceBundle\Controller;

use FOM\ManagerBundle\Configuration\Route as ManagerRoute;
use Mapbender\DataSourceBundle\Component\FeatureTypeService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Mapbender application management
 *
 * @ManagerRoute("datastore/")
 */
class DataStoreController extends Controller
{
    /**
     * @ManagerRoute("{page}", defaults={ "page"=1 }, requirements={ "page"="\d+" }, methods={"GET"})
     */
    public function indexAction()
    {
        return $this->render('@MapbenderDataSource/DataStore/index.html.twig', array(
            'title'    => 'DataStores',
            'routeUri' => 'datastore'
        ));
    }

    /**
     * List data stores
     *
     * @ManagerRoute("list")
     */
    public function listAction()
    {
        /** @var FeatureTypeService $featureService */
        $featureService = $this->container->get("features");
        $featureTypes = $featureService->getDataStoreDeclarations();

        return new JsonResponse(array(
            'list' => $featureTypes,
        ));
    }
}
