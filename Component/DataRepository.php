<?php


namespace Mapbender\DataSourceBundle\Component;


use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\Drivers\DoctrineBaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Oracle;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Component\Drivers\SQLite;
use Mapbender\DataSourceBundle\Component\Meta\TableMeta;

/**
 * Container-unaware portions (Symfony 4+) of DataStore / FeatureType
 * @since 0.1.22
 */
class DataRepository
{
    /** @var Connection */
    protected $connection;
    /** @var string */
    protected $tableName;
    /** @var DoctrineBaseDriver */
    protected $driver;
    /** @var string */
    protected $uniqueIdFieldName;
    /** @var TableMeta|null */
    protected $tableMetaData;


    public function __construct(Connection $connection, $tableName, $idColumnName)
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->uniqueIdFieldName = $idColumnName;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @return TableMeta
     */
    protected function getTableMetaData()
    {
        if (!$this->tableMetaData) {
            $this->tableMetaData = $this->getDriver()->loadTableMeta($this->connection, $this->tableName);
        }
        return $this->tableMetaData;
    }

    /**
     * Get current driver instance
     *
     * @return DoctrineBaseDriver|Geographic
     * @todo 0.2.0: Make this method protected (breaks mapbender/search)
     */
    public function getDriver()
    {
        if (!$this->driver) {
            $this->driver = $this->driverFactory($this->connection);
        }
        return $this->driver;
    }

    /**
     * Get unique ID field name
     *
     * @return string
     */
    public function getUniqueId()
    {
        return $this->uniqueIdFieldName;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function createQueryBuilder()
    {
        return $this->connection->createQueryBuilder();
    }

    /**
     * @param Connection $connection
     * @return DoctrineBaseDriver
     * @throws \Doctrine\DBAL\DBALException
     * @throws \RuntimeException on incompatible platform
     */
    protected function driverFactory(Connection $connection)
    {
        $platformName = $connection->getDatabasePlatform()->getName();
        switch ($platformName) {
            case 'sqlite';
                $driver = new SQLite($connection);
                break;
            case 'postgresql';
                $driver = new PostgreSQL($connection);
                break;
            case 'oracle';
                $driver = new Oracle($connection);
                break;
            default:
                throw new \RuntimeException("Unsupported DBAL platform " . print_r($platformName, true));
        }
        return $driver;
    }
}
