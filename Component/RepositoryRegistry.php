<?php


namespace Mapbender\DataSourceBundle\Component;


use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;

/**
 * Container-unaware (Symfony 4+) portions of DataStoreService / FeatureTypeService
 *
 * @since 0.1.22
 */
abstract class RepositoryRegistry
{
    /** @var ConnectionRegistry */
    protected $connectionRegistry;
    /** @var EventProcessor */
    protected $eventProcessor;
    /** @var mixed[][] */
    protected $repositoryConfigs;
    /** @var object[] */
    protected $repositories;

    /**
     * @param ConnectionRegistry $connectionRegistry
     * @param EventProcessor $eventProcessor
     * @param mixed[][] $repositoryConfigs
     */
    public function __construct(ConnectionRegistry $connectionRegistry,
                                EventProcessor $eventProcessor,
                                array $repositoryConfigs)
    {
        $this->connectionRegistry = $connectionRegistry;
        $this->eventProcessor = $eventProcessor;
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

    abstract public function dataStoreFactory(array $config);

    /**
     * @param string $name
     * @return object
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

    /**
     * @return EventProcessor
     */
    public function getEventProcessor()
    {
        return $this->eventProcessor;
    }
}
