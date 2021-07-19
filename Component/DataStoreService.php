<?php
namespace Mapbender\DataSourceBundle\Component;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStoreService extends RepositoryRegistry
{
    /** @var DataStore[] */
    protected $storeList = array();
    /** @var ContainerInterface */
    protected $container;
    /** @var null|string */
    protected $declarationPath;

    /**
     * @param ContainerInterface $container
     * @param string $declarationPath container param key for data store configuration array
     */
    public function __construct(ContainerInterface $container, $declarationPath = 'dataStores')
    {
        /** @var RegistryInterface $registry */
        $registry = $container->get('doctrine');
        parent::__construct($registry);

        $this->container = $container;
        $this->declarationPath = $declarationPath;
    }

    /**
     * Get store by name
     *
     * @param string $name
     * @return DataStore
     */
    public function get($name)
    {
        return $this->getDataStoreByName($name);
    }

    /**
     * @param string $name
     * @return DataStore
     * @since 0.1.15
     */
    public function getDataStoreByName($name)
    {
        if (!isset($this->storeList[$name])) {
            $configs = $this->getDataStoreDeclarations();
            $this->storeList[$name] = $this->dataStoreFactory($configs[$name]);
        }
        return $this->storeList[$name];
    }

    /**
     * @param mixed[] $config
     * @return DataStore
     * @since 0.1.15
     */
    public function dataStoreFactory(array $config)
    {
        // @todo: stop injecting full container into DataStore
        return new DataStore($this->container, $config);
    }

    /**
     * @return array
     * @deprecated remove in 0.2.0; you can't really do anything with the return value anyway
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
        $paramKey = $this->declarationPath;
        return $this->container->getParameter($paramKey);
    }
}
