<?php
namespace Mapbender\DataSourceBundle\Controller;

use FOM\ManagerBundle\Configuration\Route;
use FOM\ManagerBundle\Configuration\Route as ManagerRoute;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Mapbender application management
 *
 * @Route("datastore/")
 */
class DataStoreController extends BaseController
{
    /**
     * Renders the layer service repository.
     *
     * @ManagerRoute("{page}", defaults={ "page"=1 }, requirements={ "page"="\d+" })
     * @Method({ "GET" })
     * @Template
     */
    public function indexAction($page)
    {
        return array(
            'title' => 'Verbindungen',
        );
    }

    /**
     * Renders the layer service repository.
     *
     * @ManagerRoute("list")
     */
    public function listAction()
    {
        $request        = $this->getRequestData();
        $kernel         = $this->container->get("kernel");
        $configPath     = $kernel->getRootDir() . "/config";


        return new JsonResponse(array(
            'list' => array(),
            'title'   => 'Verbindungen',
            'request' => $request,
            'path'    => $configPath
        ));
    }

}
