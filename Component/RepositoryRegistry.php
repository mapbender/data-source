<?php


namespace Mapbender\DataSourceBundle\Component;


use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;

/**
 * Container-unaware (Symfony 4+) portions of DataStoreService / FeatureTypeService
 *
 * @since 0.1.22
 */
class RepositoryRegistry
{
    /** @var ConnectionRegistry */
    protected $connectionRegistry;
    /** @var mixed[][] */
    protected $repositoryConfigs;

    /**
     * @param ConnectionRegistry $connectionRegistry
     * @param mixed[][] $repositoryConfigs
     */
    public function __construct(ConnectionRegistry $connectionRegistry, array $repositoryConfigs)
    {
        $this->connectionRegistry = $connectionRegistry;
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
}
