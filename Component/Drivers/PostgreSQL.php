<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class PostgreSQL
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class PostgreSQL extends DoctrineBaseDriver implements Geographic
{
    /**
     * Insert data item
     *
     * @param array|DataItem $item
     * @return DataItem
     */
    public function insert($item)
    {
        $dataItem = parent::insert($item);
        if ($dataItem->getId() < 1) {
            $lastId = $this->connection->fetchColumn("SELECT
                currval(
                    pg_get_serial_sequence('" . $this->tableName
                . "','" . $this->getUniqueId() . "')
                )");
            $dataItem->setId($lastId);
        }
        return $dataItem;
    }

    /**
     * Add geometry column
     *
     * @param string $tableName
     * @param string $type
     * @param string $srid
     * @param string $geomFieldName
     * @param string $schemaName
     * @param int    $dimensions
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public function addGeometryColumn($tableName,
        $type,
        $srid,
        $geomFieldName = "geom",
        $schemaName = "public",
        $dimensions = 2)
    {
        $connection = $this->getConnection();
        return $this->connection->exec("SELECT AddGeometryColumn("
            . $connection->quote($schemaName) . ','
            . $connection->quote($tableName) . ','
            . $connection->quote($geomFieldName) . ','
            . $srid . ','
            . $connection->quote($type) . ','
            . $dimensions
            . ')');
    }

    /**
     * @param        $name
     * @param string $idColumn
     * @param bool   $dropBeforeCreate
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public function createTable($name, $idColumn = 'id', $dropBeforeCreate = false)
    {
        $db = $this->connection;
        if ($dropBeforeCreate) {
            $this->dropTable($name);
        }

        return $db->exec("CREATE TABLE IF NOT EXISTS "
            . $db->quoteIdentifier($name)
            . " (" . $db->quoteIdentifier($idColumn)
            . " SERIAL PRIMARY KEY)");
    }

    /**
     * @param $name
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function dropTable($name)
    {
        $db = $this->connection;
        return $db->exec("DROP TABLE IF EXISTS " . $db->quoteIdentifier($name));
    }

}