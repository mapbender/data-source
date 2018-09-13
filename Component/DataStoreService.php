<?php
namespace Mapbender\DataSourceBundle\Component;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Features service handles feature types
 *
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @package   Mapbender\CoreBundle\Component
 */
class DataStoreService
{
    /**
     * @var DataStore[] feature types
     */
    protected $storeList = array();
    /** @var ContainerInterface */
    protected $container;
    /** @var null|string */
    protected $declarationPath;

    /**
     * @param ContainerInterface $container
     * @param string $declarationPath container param key or file name; treated as file name if it contains slash(es)
     */
    public function __construct(ContainerInterface $container, $declarationPath = null)
    {
        $this->container = $container;
        $this->declarationPath = $declarationPath;
    }

    /**
     * Get store by name
     *
     * @param $name String data store name
     * @return DataStore|null
     */
    public function get($name)
    {
        if (!isset($this->storeList[ $name ])) {
            $configs = $this->getDataStoreDeclarations();
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

    public function getDataStoreDeclarations()
    {
        $paramKey = $this->declarationPath ?: 'dataStores';
        return $this->container->getParameter($paramKey);
    }
}
