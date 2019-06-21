<?php
namespace Mapbender\DataSourceBundle\Controller;

use FOM\ManagerBundle\Configuration\Route as ManagerRoute;
use Mapbender\DataSourceBundle\Component\FeatureTypeService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ConnectionException;

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
        $featureTypes   = $featureService->getFeatureTypeDeclarations();
        $hosts = [];
        $this->container->get("doctrine")->getManagers();
        $availableDrivers = DriverManager::getAvailableDrivers();
        
        $this->container->get("doctrine")->getManagers()['default']->getConnection();
        $connectionName = "default";
        return new JsonResponse(array(
           "connections" => $this->container->get("doctrine")->getConnections(),
           
           
        ));
    }
    
    /**
     * List data stores
     *
     * @ManagerRoute("getConnections")
     */
    public function getConnections()    {
    
        $connectionNames = [];
        foreach (array_keys($this->container->get("doctrine")->getConnections()) as $connection ){
            $connectionNames[] = $connection;//->getParams();
        }
        
        return new JsonResponse(array(
            "connections" => $connectionNames
            
        
        ));
    }
    /**
     * @ManagerRoute("getTableNames/{connectionName}")
     */
    
    public function getTableNames($connectionName)
    {
        
        $tableNames = [];
        foreach ($this->container->get("doctrine.dbal.{$connectionName}_connection")->getSchemaManager()->listTables() as $table ){
            $tableNames[] = $table->getName();
        }
        
        return new JsonResponse(array(
            "tableNames" => $tableNames,
        ));
    }
    
    /**
     * @ManagerRoute("getColumns/{connectionName}/{tableName}")
     */
    
    public function getColumns($connectionName, $tableName)
    {
    
        $columns = [];
        foreach ($this->container->get("doctrine.dbal.{$connectionName}_connection")->getSchemaManager()->listTableColumns($tableName) as $column ){
            $columns[] = array( "name" => $column->getName(), "type" => $column->getType()->getName()) ;
        }
        
        return new JsonResponse(array(
            "columns" => $columns,
        ));
    }
    
    /**
     * @ManagerRoute("getHealth/")
     */
    
    public function getHealth( )
    {
        
       $managerRegistry = $this->get('doctrine');
       $healthCheckList = [];
       foreach ($managerRegistry->getConnections() as $key => $connection) {
           try {
             $status = $connection->ping();
             $errorText = '';
           }
           catch (\Exception $e) {
            $status = false;
            $errorText = $e->getMessage();
           }
           $healthCheckList[] = array(
               "Connection Name" => $key,
               "Status" => $status,
               "Error Text" => $errorText,
               "Driver" => $connection->getDriver()->getName(),
           );
       }
      
        
        return new JsonResponse(array(
            "connectionHealth" => $healthCheckList
        ));
    }
    
    
    /** 
     * @ManagerRoute("showHealthStatus") 
     */
    
    public function showHealthStatus(){
        return $this->render('@MapbenderDataSource/DataStore/health.html.twig', array(
            'title'    => 'DataStores',
    
        ));
    }
    
}
