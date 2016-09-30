<?php
namespace Mapbender\DataSourceBundle\Controller;

use Doctrine\DBAL\Connection;
use FOM\ManagerBundle\Configuration\Route;
use FOM\ManagerBundle\Configuration\Route as ManagerRoute;
use Mapbender\DataSourceBundle\Component\FeatureType;
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
            'title'    => 'Verbindungen',
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
        $featureService = $this->container->get("features");
        $featureTypes   = $featureService->getFeatureTypeDeclarations();

        return new JsonResponse(array(
            'list' => $featureTypes,
        ));
    }

    /**
     * @ManagerRoute("connection/list")
     */
    public function listDatabaseConnectionsAction()
    {
        /** @var Connection $connection */
        $registry        = $this->getDoctrine();
        $connectionNames = $registry->getConnectionNames();
        $connections     = array();
        foreach ($connectionNames as $id => $name) {
            $connection         = $this->container->get($name);
            $connections[ $id ] = array(
                'id'   => $id,
                'name' => $name,
                'type' => $connection->getDatabasePlatform()->getName(),
            );
        }
        return new JsonResponse(array(
            'list' => $connections,
        ));
    }
    /**
     * @ManagerRoute("connection/schemas")
     */
    public function listDatabaseSchemasAction()
    {
        /** @var Connection $connection */
        $request         = $this->getRequestData();
        $connectionId    = $request['id'];
        $registry        = $this->getDoctrine();
        $connectionNames = $registry->getConnectionNames();
        $connections     = array();

        if (!in_array($connectionId, array_values($connectionNames))) {
            throw new \Exception('Erronious connection');
        }

        $connection = $this->container->get($connectionId);
        // PHP

        return new JsonResponse(array(
            'list' => $connections,
        ));
    }

    /**
     * Save data store
     *
     * @ManagerRoute("save")
     */
    public function saveAction()
    {
        $errors               = array();
        $featureService       = $this->container->get("features");
        $featureTypes         = $featureService->getFeatureTypeDeclarations();
        $request              = $this->getRequestData();
        $dataStoreDeclaration = isset($request["item"]) && is_array($request["item"]) ? $request["item"] : null;

        if ($dataStoreDeclaration) {
            throw new \Exception('No schema');
        }

        $featureType        = new FeatureType($this->container, $dataStoreDeclaration);
        $featureTypeManager = $this->container->get('features');
        $featureTypeManager->save($featureType);

        return new JsonResponse(array(
            'list' => $featureTypes,
        ));
    }

}
