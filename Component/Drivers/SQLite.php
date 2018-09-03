<?php

namespace Mapbender\DataSourceBundle\Component\Drivers;

use Eslider\SpatialiteShellDriver;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Entity\Feature;

/**
 * Class SQLite
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class SQLite extends PostgreSQL implements Geographic
{
    /**
     * Get spatial driver instance
     *
     * @return SpatialiteShellDriver|null
     */
    public function getSpatialDriver()
    {
        static $driver = null;

        if (!$driver) {
            $dbPath = isset($this->settings['path']) ? $this->settings['path'] : $this->container->get('kernel')->getRootDir() . "/app/db/";
            $driver = new SpatialiteShellDriver($dbPath);
        }

        return $driver;
    }

    /**
     * Get table fields
     *
     * Info: $schemaManager->listTableColumns($this->tableName) doesn't work if fields are geometries!
     *
     * @throws \Doctrine\DBAL\DBALException
     * @return array field names
     */
    public function getStoreFields()
    {
        $schemaManager = $this->connection->getDriver()->getSchemaManager($this->connection);
        $columns       = array();
        $sql           = $schemaManager->getDatabasePlatform()->getListTableColumnsSQL($this->tableName, $this->connection->getDatabase());
        $all           = $this->connection->fetchAll($sql);

        foreach ($all as $fieldInfo) {
            $columns[] = $fieldInfo["name"];
        }
        return $columns;
    }

    /**
     * @inheritdoc
     */
    public function search(array $criteria = array())
    {
        $sql        = $this->getSearchQueryBuilder($criteria)->getSQL();
        $rows       = $this->getSpatialDriver()->query($sql);
        $hasResults = count($rows) > 0;

        // Cast array to DataItem array list
        if ($hasResults) {
            $this->prepareResults($rows);
        }

        return $rows;
    }

    /**
     * Add geometry column
     *
     * @param        $tableName
     * @param        $type
     * @param        $srid
     * @param string $geomFieldName
     * @param string $schemaName
     * @param int    $dimensions
     * @return mixed
     */
    public function addGeometryColumn($tableName,
        $type,
        $srid,
        $geomFieldName = "geom",
        $schemaName = "public",
        $dimensions = 2)
    {
        $spatialDriver = $this->getSpatialDriver();
        return $spatialDriver->addGeometryColumn($tableName, $geomFieldName, $srid, $type);
    }

    /**
     * Get table geometry type
     *
     * @param        $tableName
     * @param string $schema
     * @return mixed
     */
    public function getTableGeomType($tableName, $schema = null)
    {
        $connection = $this->getSpatialDriver();
        if (strpos($tableName, '.')) {
            list($schema, $tableName) = explode('.', $tableName);
        }
        $_schema = $schema ? $connection->quote($schema) : 'current_schema()';

        $type = $connection->query("SELECT \"type\"
                FROM geometry_columns
                WHERE f_table_schema = " . $_schema . "
                AND f_table_name = " . $connection->quote($tableName))->fetchColumn();
        return $type;
    }

    /**
     * @param      $ewkt
     * @param null $srid
     * @return mixed
     * @internal param $wkt
     */
    public function transformEwkt($ewkt, $srid = null)
    {
        $db      = $this->getSpatialDriver();
        $type    = $this->getTableGeomType($this->getTableName());
        $wktType = static::getWktType($ewkt);

        if ($type
            && $wktType != $type
            && in_array(strtoupper($wktType), Feature::$simpleGeometries)
            && in_array(strtoupper($type), Feature::$complexGeometries)
        ) {
            $ewkt = 'SRID=' . $srid . ';' . $db->fetchColumn("SELECT ST_ASTEXT(ST_TRANSFORM(ST_MULTI(" . $db->quote($ewkt) . "),$srid))");
        }

        $srid = is_numeric($srid) ? intval($srid) : $db->quote($srid);
        $ewkt = $db->quote($ewkt);

        return $db->fetchColumn("SELECT ST_TRANSFORM(ST_GEOMFROMTEXT($ewkt), $srid)");
    }

    /**
     * Get WKB geometry attribute as WKT
     *
     * @param  string $tableName
     * @param  string $geomFieldName
     * @return string SQL
     */
    public function findGeometryFieldSrid($tableName, $geomFieldName)
    {
        $connection = $this->getSpatialDriver();
        $schemaName = "current_schema()";
        if (strpos($tableName, ".")) {
            list($schemaName, $tableName) = explode('.', $tableName);
            $schemaName = $connection->quote($schemaName);
        }

        return $connection->fetchColumn("SELECT Find_SRID(" . $schemaName . ", 
            " . $connection->quote($tableName) . ", 
            " . $connection->quote($geomFieldName) . ")");
    }
}