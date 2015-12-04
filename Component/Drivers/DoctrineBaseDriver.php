<?php
/**
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */

namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class DoctrineBaseDriver
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DoctrineBaseDriver extends BaseDriver implements IDriver
{
    const MAX_RESULTS = 100;

    /** @var Connection */
    public $connection;

    /**
     * @var string Table name
     */
    protected $tableName;

    /**
     * @var string SQL where filter
     */
    protected $sqlFilter;

    /**
     * Open connection by name$settings
     *
     * @param $name
     * @return $this
     */
    public function connect($name = "default")
    {
        $this->connection = $this->container->get("doctrine.dbal.{$name}_connection");
        return $this;
    }

    /**
     * @param $id
     * @return DataItem
     */
    public function get($id)
    {
        // TODO: Implement get() method.
    }

    /**
     * Save the data
     *
     * @param $data
     * @return mixed
     */
    public function save(DataItem $data)
    {
        // TODO: Implement save() method.
    }

    /**
     * Remove by ID
     *
     * @param $id
     * @return mixed
     */
    public function remove($id)
    {
        // TODO: Implement remove() method.
    }


    /**
     * Is the driver connected an ready to interact?
     *
     * @return bool
     */
    public function isReady()
    {
        return $this->connection && $this->connection->isConnected();
    }

    /**
     * Has permission to read?
     *
     * @return bool
     */
    public function canRead()
    {
        return $this->isReady(); // TODO: implement user access check
    }

    /**
     * Has permission to write?
     *
     * @return bool
     */
    public function canWrite()
    {
        return $this->isReady(); // TODO: implement user access check
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * @param string  $statement The SQL query to be executed.
     * @param array   $params    The prepared statement params.
     * @param integer $colnum    The 0-indexed column number to retrieve.
     *
     * @return mixed
     */
    public function fetchColumn($statement, array $params = array(), $colnum = 0)
    {
        $this->connection->fetchColumn($statement, $params, $colnum);
    }

    /**
     * Get version
     */
    public function getVersion()
    {
        $this->fetchColumn("SELECT version()");
    }

    /**
     * Get DBAL Connections
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Set table name
     *
     * @param $name
     * @return $this
     */
    public function setTable($name)
    {
        $this->tableName = $name;
        return $this;
    }

    /**
     * Get table fields
     *
     * Info: $schemaManager->listTableColumns($this->tableName) doesn't work if fields are geometries!
     *
     * @throws \Doctrine\DBAL\DBALException
     * @return array field names
     */
    public function getTableFields()
    {
        $schemaManager = $this->connection->getDriver()->getSchemaManager($this->connection);
        $columns       = array();
        $sql           = $schemaManager->getDatabasePlatform()->getListTableColumnsSQL($this->tableName, $this->connection->getDatabase());
        foreach ($this->connection->fetchAll($sql) as $fieldInfo) {
            $columns[] = $fieldInfo["field"];
        }
        return $columns;
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName()
    {
        static $name = null;
        if (!$name) {
            $name = $this->connection->getDatabasePlatform()->getName();
        }
        return $name;
    }


    /**
     * Get query builder prepared to select from the source table
     *
     * @return QueryBuilder
     */
    public function getSelectQueryBuilder()
    {
        $connection   = $this->getConnection();
        $attributes   = array_merge(array($this->uniqueId), $this->fields);
        $queryBuilder = $connection->createQueryBuilder()->select($attributes)->from($this->tableName, 't');
        return $queryBuilder;
    }

    /**
     * Search by criteria
     *
     * @param array $criteria
     * @return DataItem[]
     */
    public function search(array $criteria = array())
    {

        /** @var Statement $statement */
        $maxResults   = isset($criteria['maxResults']) ? intval($criteria['maxResults']) : self::MAX_RESULTS;
        $where        = isset($criteria['where']) ? $criteria['where'] : null;
        $queryBuilder = $this->getSelectQueryBuilder();
        //        $returnType   = isset($criteria['returnType']) ? $criteria['returnType'] : null;

        // add filter (https://trac.wheregroup.com/cp/issues/3733)
        if (!empty($this->sqlFilter)) {
            $queryBuilder->andWhere($this->sqlFilter);
        }

        // add second filter (https://trac.wheregroup.com/cp/issues/4643)
        if ($where) {
            $queryBuilder->andWhere($where);
        }

        $queryBuilder->setMaxResults($maxResults);
        // $queryBuilder->setParameters($params);
        $statement  = $queryBuilder->execute();
        $rows       = $statement->fetchAll();
        $hasResults = count($rows) > 0;

        // Convert to Feature object
        if ($hasResults) {
            $this->prepareResults($rows);
        }

        return $rows;
    }

    /**
     * Convert results to DataItem objects
     *
     * @param array $rows - Data items to be casted
     * @return DataItem[]
     */
    public function prepareResults(&$rows)
    {
        foreach ($rows as $key => &$row) {
            $row = $this->create($row);
        }
        return $rows;
    }

    /**
     * Cast DataItem by $args
     *
     * @param mixed $args
     * @return DataItem
     */
    public function create($args)
    {
        $data = null;
        if (is_object($args)) {
            if ($args instanceof DataItem) {
                $data = $args;
            } else {
                $args = get_object_vars($args);
            }
        } elseif (is_numeric($args)) {
            $args = array($this->getUniqueId() => intval($args));
        }
        return $data ? $data : new DataItem($args, $this->getUniqueId());
    }

    /**
     * Set FeatureType permanent SQL filter used by $this->search()
     * https://trac.wheregroup.com/cp/issues/3733
     *
     * @see $this->search()
     * @param $sqlFilter
     */
    public function setFilter($sqlFilter)
    {
        $this->sqlFilter = $sqlFilter;
    }
}