<?php


namespace Mapbender\DataSourceBundle\Component;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Mapbender\DataSourceBundle\Component\Drivers\DoctrineBaseDriver;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Oracle;
use Mapbender\DataSourceBundle\Component\Drivers\PostgreSQL;
use Mapbender\DataSourceBundle\Component\Drivers\SQLite;
use Mapbender\DataSourceBundle\Component\Meta\TableMeta;
use Mapbender\DataSourceBundle\Entity\DataItem;

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
    /** @var string[] */
    protected $fields;

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
     * Create empty item
     *
     * @return DataItem
     * @since 0.1.16.2
     */
    public function itemFactory()
    {
        return new DataItem(array(), $this->uniqueIdFieldName);
    }

    /**
     * @param integer|string $id
     * @return DataItem|null
     */
    public function getById($id)
    {
        $qb = $this->getSelectQueryBuilder()->setMaxResults(1);
        $qb->where($this->getUniqueId() . ' = :id');
        $qb->setParameter(':id', $id);
        $items = $this->prepareResults($qb);
        if ($items) {
            return $items[0];
        } else {
            return null;
        }
    }

    /**
     * @param DataItem $item
     * @return DataItem|null
     */
    protected function reloadItem($item)
    {
        return $this->getById($item->getId());
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
     * Get query builder prepared to select from the source table
     *
     * @return QueryBuilder
     */
    protected function getSelectQueryBuilder()
    {
        $connection = $this->getConnection();
        $qb = $this->createQueryBuilder();
        $qb->from($this->getTableName(), 't');
        $fields = array_merge(array($this->getUniqueId()), $this->getFields());

        foreach ($fields as $field) {
            if (is_array($field)) {
                // @todo: specify, document
                $alias = current(array_keys($field));
                $expression = current(array_values($field));
                $qb->addSelect("$expression AS " . $connection->quoteIdentifier($alias));
            } else {
                // Quote fields, unless they are expressions.
                // Bare-bones detection for
                // * literal * (as in SELECT * FROM ...)
                // * SQL functions (round brackets)
                // * String literals
                // * Pre-quoted identifiers (Backtick on MySQL, double-quote on PostgreSQL)
                if (!preg_match('#["\'`()]#', $field) && $field !== '*') {
                    $field = $connection->quoteIdentifier($field);
                }
                $qb->addSelect($field);
            }
        }

        return $qb;
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
     * @return string[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return QueryBuilder
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

    /**
     * @param mixed $id
     * @return array
     */
    protected function idToIdentifier($id)
    {
        $uniqueId = $this->uniqueIdFieldName;
        return array($uniqueId => $id);
    }

    /**
     * @param DataItem $item
     * @return mixed[]
     */
    protected function getSaveData(DataItem $item)
    {
        return $item->toArray();
    }

    /**
     * Convert database rows to DataItem objects
     *
     * @param QueryBuilder $queryBuilder
     * @return DataItem[]
     */
    protected function prepareResults(QueryBuilder $queryBuilder)
    {
        $uniqueId = $this->getUniqueId();
        $items = array();
        foreach ($queryBuilder->execute()->fetchAll() as $row) {
            $item = new DataItem(array(), $uniqueId);
            $item->setAttributes($row);
            $items[] = $item;
        }
        return $items;
    }
}
