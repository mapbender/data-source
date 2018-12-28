<?php
namespace Mapbender\DataSourceBundle\Controller;

use FOM\ManagerBundle\Configuration\Route as ManagerRoute;
use Mapbender\DataSourceBundle\Component\FeatureTypeService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
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
     * @ManagerRoute("{page}", defaults={ "page"=1 }, requirements={ "page"="\d+" })
     * @Method({ "GET" })
     * @Template
     */
    public function indexAction($page)
    {
        return array(
            'title'    => 'DataStores',
            'routeUri' => 'datastore'
        );
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
        $featureTypes   = $featureService->getFeatureTypeDeclarations();

        return new JsonResponse(array(
            'list' => $featureTypes,
        ));
    }
}
