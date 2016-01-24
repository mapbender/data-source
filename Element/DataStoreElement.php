<?php
namespace Mapbender\DataSourceBundle\Element;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use FOM\CoreBundle\Component\ExportResponse;
use Mapbender\CoreBundle\Asset\NamedAssetCache;
use Mapbender\CoreBundle\Component\Application;
use Mapbender\CoreBundle\Element\HTMLElement;
use Mapbender\CoreBundle\Entity\Element;
use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DataStoreElement
 *
 * @package Mapbender\DataSourceBundle\Element
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStoreElement extends HTMLElement
{
    /**
     * The constructor.
     *
     * @param Application        $application The application object
     * @param ContainerInterface $container   The container object
     * @param Element            $entity
     */
    public function __construct(Application $application, ContainerInterface $container, Element $entity)
    {
        parent::__construct($application, $container, $entity);
    }

    /**
     * @inheritdoc
     */
    static public function getClassTitle()
    {
        return "Data store";
    }

    /**
     * @inheritdoc
     */
    static public function getClassDescription()
    {
        return "Data store element";
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbDataStore';
    }

    /**
     * @inheritdoc
     */
    static public function getTags()
    {
        return array();
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultConfiguration()
    {
        return array();
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\DataSourceBundle\Element\Type\DataStoreAdminType';
    }

    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderDataSourceBundle:ElementAdmin:datastore.html.twig';
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        return /** @lang XHTML */
            '<div
                id="' . $this->getId() . '"
                class="mb-element mb-element-data-store modal-body"
                title="' . _($this->getTitle()) . '">
                   <div class="title"></div>
                   <select class="selector"></select>
            </div>';
    }

    /**
     * @inheritdoc
     */
    static public function listAssets()
    {
        return array(
            'css' => array(
                '/bundles/mapbenderdatasource/sass/element/data.store.element.scss'
            ),
            'js'  => array(
                'datastore.element.js'
            ),
        );
    }

    /**
     * Prepare form items for each scheme definition
     * Optional: get featureType by name from global context.
     *
     * @inheritdoc
     */
    public function getConfiguration()
    {
        $configuration            = parent::getConfiguration();
        $configuration['debug']   = isset($configuration['debug']) ? $configuration['debug'] : false;
        $configuration['fileUri'] = $this->container->getParameter("mapbender.uploads_dir") . "/data-store";

        if (isset($configuration["schemes"]) && is_array($configuration["schemes"])) {
            foreach ($configuration["schemes"] as $key => &$scheme) {
                if (is_string($scheme['dataStore'])) {
                    $storeId                   = $scheme['dataStore'];
                    $dataStore                 = $this->container->getParameter('dataStores');
                    $scheme['dataStore']       = $dataStore[ $storeId ];
                    $scheme['dataStore']["id"] = $storeId;
                    //$dataStore = new DataStore($this->container, $configuration['source']);
                }
                if (isset($scheme['formItems'])) {
                    $scheme['formItems'] = $this->prepareItems($scheme['formItems']);
                }
            }
        }
        return $configuration;
    }

    /**
     * @inheritdoc
     */
    public function httpAction($action)
    {
        /**
         * @var $requestService Request
         */
        $configuration   = $this->getConfiguration();
        $requestService  = $this->container->get('request');
        $request         = json_decode($requestService->getContent(), true);
        $schemas         = $configuration["schemes"];
        $debugMode       = $configuration['debug'] || $this->container->get('kernel')->getEnvironment() == "dev";
        $schemaName      = isset($request["schema"]) ? $request["schema"] : $requestService->get("schema");
        $defaultCriteria = array('returnType' => 'FeatureCollection',
                                 'maxResults' => 2500);
        $schema          = $schemas[ $schemaName ];


        if (is_array($schema['dataStore'])) {
            $dataStore = new DataStore($this->container, $schema['dataStore']);
        } else {
            throw new \Exception("DataStore setup is not correct");
        }

        $results = array();

        switch ($action) {
            case 'select':
                foreach ($dataStore->search(array_merge($defaultCriteria, $request)) as &$dataItem){
                    $results[] = $dataItem->toArray();
                }
                break;

            case 'save':
                // save once
                if (isset($request['feature'])) {
                    $request['features'] = array($request['feature']);
                }

                try {
                    // save collection
                    if (isset($request['features']) && is_array($request['features'])) {
                        foreach ($request['features'] as $feature) {
                            /**
                             * @var $feature Feature
                             */
                            $featureData = $this->prepareQueredFeatureData($feature, $schema['formItems']);

                            foreach ($dataStore->getFileInfo() as $fileConfig) {
                                if (!isset($fileConfig['field']) || !isset($featureData["properties"][ $fileConfig['field'] ])) {
                                    continue;
                                }
                                $url                                               = $dataStore->getFileUrl($fileConfig['field']);
                                $requestUrl                                        = $featureData["properties"][ $fileConfig['field'] ];
                                $newUrl                                            = str_replace($url . "/", "", $requestUrl);
                                $featureData["properties"][ $fileConfig['field'] ] = $newUrl;
                            }

                            $feature = $dataStore->save($featureData);
                            $results = array_merge($dataStore->search(array(
                                'where' => $dataStore->getUniqueId() . '=' . $feature->getId())));
                        }
                    }
                    $results = $dataStore->toFeatureCollection($results);
                } catch (DBALException $e) {
                    $message = $debugMode ? $e->getMessage() : "Feature can't be saved. Maybe something is wrong configured or your database isn't available?\n" .
                        "For more information have a look at the webserver log file. \n Error code: " . $e->getCode();
                    $results = array('errors' => array(
                        array('message' => $message, 'code' => $e->getCode())
                    ));
                }

                break;

            case 'delete':
                $results = $dataStore->remove($request['feature']);
                break;

            case 'file-upload':
                $fieldName                  = $requestService->get('field');
                $urlParameters              = array('schema' => $schemaName,
                                                    'fid'    => $requestService->get('fid'),
                                                    'field'  => $fieldName);
                $serverUrl                  = preg_replace('/\\?.+$/', "", $_SERVER["REQUEST_URI"]) . "?" . http_build_query($urlParameters);
                $uploadDir                  = $dataStore->getFilePath($fieldName);
                $uploadUrl                  = $dataStore->getFileUrl($fieldName) . "/";
                $urlParameters['uploadUrl'] = $uploadUrl;
                $uploadHandler              = new Uploader(array(
                    'upload_dir'                   => $uploadDir . "/",
                    'script_url'                   => $serverUrl,
                    'upload_url'                   => $uploadUrl,
                    'accept_file_types'            => '/\.(gif|jpe?g|png)$/i',
                    'print_response'               => false,
                    'access_control_allow_methods' => array(
                        'OPTIONS',
                        'HEAD',
                        'GET',
                        'POST',
                        'PUT',
                        'PATCH',
                        //                        'DELETE'
                    ),
                ));
                $results                    = array_merge($uploadHandler->get_response(), $urlParameters);

                break;

            default:
                $results = array(
                    array('errors' => array(
                        array('message' => $action . " not defined!")
                    ))
                );
        }

        return new JsonResponse($results);

    }
}