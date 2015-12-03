<?php
/**
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */

namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class DoctrineBaseDriver
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DoctrineBaseDriver extends BaseDriver implements IDriver
{
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
        // TODO: Implement isReady() method.
    }

    /**
     * Has permission to read?
     *
     * @return bool
     */
    public function canRead()
    {
        // TODO: Implement canRead() method.
    }

    /**
     * Has permission to write?
     *
     * @return bool
     */
    public function canWrite()
    {
        // TODO: Implement canWrite() method.
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
     * Get DBAL Connection
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
     * @throws \Doctrine\DBAL\DBALException
     * @return array field names
     */
    public function getTableFields()
    {
        return array();
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
}