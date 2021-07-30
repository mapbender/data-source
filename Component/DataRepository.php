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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Container-unaware portions (Symfony 4+) of DataStore / FeatureType
 * @since 0.1.22
 */
class DataRepository
{
    /** @var Connection */
    protected $connection;
    /** @var TokenStorageInterface */
    protected $tokenStorage;
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

    public function __construct(Connection $connection, TokenStorageInterface $tokenStorage, $tableName, $idColumnName)
    {
        $this->connection = $connection;
        $this->tokenStorage = $tokenStorage;
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
     * Get by ID list
     *
     * @param mixed[] $ids
     * @return array[]|DataItem[]
     * @todo 0.2.0: remove parametric return type support (always return DataItem[])
     */
    public function getByIds($ids)
    {
        $queryBuilder = $this->getSelectQueryBuilder();
        $connection   = $queryBuilder->getConnection();
        $condition = $queryBuilder->expr()->in($this->uniqueIdFieldName, array_map(array($connection, 'quote'), $ids));
        $queryBuilder->where($condition);
        $results = $this->prepareResults($queryBuilder);
        if (\func_num_args() > 1 && !\func_get_arg(1)) {
            @trigger_error("Deprecated: array return support in getByIds is deprecated. Run ->getAttributes() on the returned items yourself.", E_USER_DEPRECATED);
            foreach ($results as $k => $item) {
                $results[$k] = $item->getAttributes();
            }
        }
        return $results;
    }

    /**
     * @param DataItem $item
     * @return DataItem
     */
    public function insertItem(DataItem $item)
    {
        $values = $this->prepareStoreValues($item, $item->getAttributes());
        unset($values[$this->uniqueIdFieldName]);
        $values = $this->getTableMetaData()->prepareInsertData($values);
        $id = $this->getDriver()->insert($this->connection, $this->getTableName(), $values, $this->uniqueIdFieldName);
        $item->setId($id);
        return $item;
    }

    public function updateItem(DataItem $item)
    {
        $values = $this->prepareStoreValues($item, $item->getAttributes());
        $identifier = $this->idToIdentifier($item->getId());
        $values = $this->getTableMetaData()->prepareUpdateData($values);
        $this->getDriver()->update($this->connection, $this->getTableName(), $values, $identifier);
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
            $qb->addSelect($connection->quoteIdentifier($field));
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

    protected function prepareStoreValues(DataItem $item, array $values)
    {
        return $values;
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
