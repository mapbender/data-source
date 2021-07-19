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

    /**
     * @param ConnectionRegistry $connectionRegistry
     */
    public function __construct(ConnectionRegistry $connectionRegistry)
    {
        $this->connectionRegistry = $connectionRegistry;
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
