<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Component\Expression;
use Mapbender\DataSourceBundle\Component\Meta\Loader\AbstractMetaLoader;
use Mapbender\DataSourceBundle\Component\Meta\Loader\OracleMetaLoader;
use Mapbender\DataSourceBundle\Component\Meta\Loader\PostgreSqlMetaLoader;
use Mapbender\DataSourceBundle\Component\Meta\Loader\SqliteMetaLoader;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class DoctrineBaseDriver extends BaseDriver
{
    /** @var Connection */
    public $connection;

    /** @var AbstractMetaLoader|null */
    protected $metaDataLoader;

    public function __construct(Connection $connection, DataStore $repository)
    {
        $this->connection = $connection;
        $this->metaDataLoader = $this->initMetaDataLoader($connection);

        parent::__construct($repository);
        if (!$repository->getTableName()) {
            throw new \LogicException("Cannot initialize " . get_class($this) . " with empty table name");
        }
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
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
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function canRead()
    {
        return $this->isReady();
    }

    /**
     * Has permission to write?
     *
     * @return bool
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function canWrite()
    {
        return $this->isReady();
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * @param string  $statement The SQL query to be executed.
     * @param array   $params    The prepared statement params.
     * @param integer $columnNum The 0-indexed column number to retrieve.
     *
     * @return mixed
     */
    public function fetchColumn($statement, array $params = array(), $columnNum = 0)
    {
        return $this->connection->fetchColumn($statement, $params, $columnNum);
    }


    /**
     * Fetches single-column SQL result set.
     *
     * @param string $statement
     * @return array
     */
    public function fetchList($statement)
    {
        $result = array();
        foreach ($this->getConnection()->fetchAll($statement) as $row) {
            $result[] = current($row);
        }
        return $result;
    }

    /**
     * Get version
     */
    public function getVersion()
    {
        $this->fetchColumn("SELECT version()");
    }

    /**
     * @param string $tableName
     * @return string[]
     */
    public function getColumnNames($tableName)
    {
        return $this->metaDataLoader->getTableMeta($tableName)->getColumNames();
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName()
    {
        return $this->connection->getDatabasePlatform()->getName();
    }

    /**
     * @return string
     *
     * @todo 0.2.0: remove repository binding and all methods requiring repository inflection
     */
    public function getTableName()
    {
        return $this->repository->getTableName();
    }

    /**
     * @param string $tableName
     * @param array $data
     * @return int the last insert id
     */
    public function insert($tableName, array $data)
    {
        $pData = $this->prepareInsertData($data);

        $sql = $this->getInsertSql($tableName, $pData[0], $pData[1]);
        $connection = $this->getConnection();
        $connection->executeQuery($sql, $pData[2]);
        return $connection->lastInsertId();
    }

    /**
     * @param mixed[] $data
     * @return array numeric with 3 entries: first: quoted column names; second: sql value expressions; third: query parameters
     */
    protected function prepareInsertData(array $data)
    {
        $connection = $this->connection;
        $columns = array();
        $sqlValues = array();
        $params = array();
        foreach ($data as $columnName => $value) {
            if ($value instanceof Expression) {
                $sqlValues[] = $value->getText();
            } else {
                // add placeholder and param binding
                $sqlValues[] = '?';
                $params[] = $this->prepareParamValue($value);
            }
            $columns[] = $connection->quoteIdentifier($columnName);
        }
        return array(
            $columns,
            $sqlValues,
            $params,
        );
    }

    protected function getInsertSql($tableName, $columns, $values)
    {
        return
            'INSERT INTO ' . $this->getConnection()->quoteIdentifier($tableName)
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES '
            . ' (' . implode(', ', $values) . ')'
        ;
    }

    /**
     * @param string $tableName
     * @param mixed[] $data
     * @param mixed[] $identifier
     * @return int rows affected
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update($tableName, array $data, array $identifier)
    {
        $connection = $this->getConnection();

        $data = array_diff_key($data, $identifier);
        if (empty($data)) {
            throw new \Exception("Can't update row without data");
        }
        $initializers = array();
        $conditions = array();
        $params = array();
        foreach ($data as $columnName => $value) {
            $columnQuoted = $connection->quoteIdentifier($columnName);
            if ($value instanceof Expression) {
                $initializers[] = "{$columnQuoted} = {$value->getText()}";
            } else {
                // add placeholder and param binding
                $initializers[] = "{$columnQuoted} = ?";
                $params[] = $this->prepareParamValue($value);
            }
        }
        foreach ($identifier as $columnName => $value) {
            $conditions[] = $connection->quoteIdentifier($columnName) . ' = ?';
            $params[] = $this->prepareParamValue($value);
        }

        $sql =
            'UPDATE ' . $connection->quoteIdentifier($tableName)
            . ' SET '
            . implode(', ', $initializers)
            . ' WHERE '
            . implode(' AND ', $conditions)
        ;
        return $connection->executeUpdate($sql, $params);
    }



    /**
     * Delete rows
     * @see \Doctrine\DBAL\Connection::delete
     *
     * @param string $tableName
     * @param mixed[] $identifier
     * @return int number of affected rows
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function delete($tableName, array $identifier)
    {
        return $this->getConnection()->delete($tableName, $identifier);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function prepareParamValue($value)
    {
        // Base driver: no transformation
        return $value;
    }

    /**
     * Attempts to extract an identifier array from whatever $arg is
     * Extracted id equivalent to DataStore::create($arg)->getId()
     *
     * @param mixed $arg
     * @return mixed[]
     */
    private function anythingToIdentifier($arg)
    {
        $uniqueId = $this->repository->getUniqueId();
        if (\is_numeric($arg)) {
            return array($uniqueId => $arg);
        } elseif (\is_object($arg)) {
            if ($arg instanceof DataItem) {
                return array($uniqueId => $arg->getId());
            } else {
                // self-delegate to array path
                return $this->anythingToIdentifier(\get_object_vars($arg));
            }
        } elseif (\is_array($arg)) {
            if (!empty($arg[$uniqueId])) {
                return array($uniqueId => $arg[$uniqueId]);
            }
        }
        // uh-oh!
        return null;
    }

    protected function initMetaDataLoader(Connection $connection)
    {
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSqlPlatform) {
            return new PostgreSqlMetaLoader($connection);
        } elseif ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            return new SqliteMetaLoader($connection);
        } elseif ($platform instanceof \Doctrine\DBAL\Platforms\OraclePlatform) {
            return new OracleMetaLoader($connection);
        } else {
            // Uh-oh. MySQL?
            return null;
        }
    }
}
