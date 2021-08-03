<?php


namespace Mapbender\DataSourceBundle\Component;


use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Mapbender\DataSourceBundle\Component\Factory\DataStoreFactory;

/**
 * Container-unaware (Symfony 4+) portions of DataStoreService / FeatureTypeService
 *
 * @since 0.1.22
 */
class RepositoryRegistry
{
    /** @var ConnectionRegistry */
    protected $connectionRegistry;
    /** @var DataStoreFactory */
    protected $factory;
    /** @var mixed[][] */
    protected $repositoryConfigs;
    /** @var DataStore[] */
    protected $repositories;

    /**
     * @param ConnectionRegistry $connectionRegistry
     * @param DataStoreFactory $factory
     * @param mixed[][] $repositoryConfigs
     */
    public function __construct(ConnectionRegistry $connectionRegistry,
                                DataStoreFactory $factory,
                                array $repositoryConfigs)
    {
        $this->connectionRegistry = $connectionRegistry;
        $this->factory = $factory;
        $this->repositoryConfigs = $repositoryConfigs;
    }

    /**
     * @param string $name
     * @return Connection
     * @since 0.0.16
     */
    public function getDbalConnectionByName($name)
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection($name);
        return $connection;
    }

    /**
     * @param array $config
     * @return DataStore
     */
    public function dataStoreFactory(array $config)
    {
        return $this->factory->fromConfig($this, $config);
    }

    /**
     * @param string $name
     * @return DataStore
     * @since 0.1.15
     */
    public function getDataStoreByName($name)
    {
        if (!$name) {
            throw new \InvalidArgumentException("Empty dataStore / featureType name " . var_export($name, true));
        }
        if (!\array_key_exists($name, $this->repositories)) {
            $this->repositories[$name] = $this->dataStoreFactory($this->repositoryConfigs[$name]);
        }
        return $this->repositories[$name];
    }

    /**
     * @return mixed[][]
     * @since 0.1.8
     */
    public function getDataStoreDeclarations()
    {
        return $this->repositoryConfigs;
    }
}
