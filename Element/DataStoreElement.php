<?php
namespace Mapbender\DataSourceBundle\Element;

use Mapbender\CoreBundle\Component\Application;
use Mapbender\CoreBundle\Element\HTMLElement;
use Mapbender\CoreBundle\Entity\Element;
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
        return "Query builder";
    }

    /**
     * @inheritdoc
     */
    static public function getClassDescription()
    {
        return "Build, list SQL queries and display result, which can be edited to.";
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbDataStoreElement';
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
        return array(
            "target" => null
        );
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
                title="' . _($this->getTitle()) . '"></div>';
    }

    /**
     * @inheritdoc
     */
    static public function listAssets()
    {
        return array(
            'js' => array(
                'datastore.element.js'
            ),
        );
    }


    /**
     * @inheritdoc
     */
    public function httpAction($action)
    {
        /** @var DataItem $dataItem */
        /** @var $requestService Request */

        $configuration   = $this->getConfiguration();
        $requestService  = $this->container->get('request');
        $defaultCriteria = array();
        $request         = $requestService->getContent() ? array_merge($defaultCriteria, json_decode($requestService->getContent(), true)) : array();

        if (isset($configuration['source']) /*&& is_array($configuration['source']) */) {
            $dataStore = $this->container->get("data.source")->get($configuration['source']);
            //$dataStore = new DataStore($this->container, $configuration['source']);
        } else {
            throw new \Exception("DataStore source settings isn't correct");
        }

        switch ($action) {
            case 'select':
                $results = array();
                foreach ($dataStore->search($request) as &$dataItem) {
                    $results[] = $dataItem->toArray();
                }
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