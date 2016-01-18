<?php
namespace Mapbender\DataSourceBundle\Component;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Features service handles feature types
 *
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @package   Mapbender\CoreBundle\Component
 */
class DataStoreService extends ContainerAware
{
    /**
     * Feature type s defined in mapbebder.yml > parameters.featureTypes
     *
     * @var DataStore[] feature types
     */
    private $storeList = array();

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }

    /**
     * Get store by name
     *
     * @param $name String data store name
     * @return DataStore
     */
    public function get($name)
    {
        static $configs = null;
        if (!isset($this->storeList[ $name ])) {
            if (!$configs) {
                $configs = $this->container->getParameter('dataStores');
            }

            //if (is_int($name)) {
            //    $keys = array_keys($configs);
            //    $name = $keys[ $name ];
            //}

            $this->storeList[ $name ] = new DataStore($this->container, $configs[ $name ]);
        }
        return $this->storeList[ $name ];
    }

    /**
     * @return array
     */
    public function listDrivers()
    {
        return array(
            'SQLite',
            'PostgreSQL',
            'YAML',
            'JSON'
        );
    }
}