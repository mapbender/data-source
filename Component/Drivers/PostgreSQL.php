<?php

namespace Mapbender\DataSourceBundle\Component\Drivers;

use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Routable;
use Mapbender\DataSourceBundle\Component\LegacyPgRouting;
use Mapbender\DataSourceBundle\Entity\Feature;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class PostgreSQL extends DoctrineBaseDriver implements Geographic, Routable
{

    public function insert(Connection $connection, $tableName, array $data, $identifier)
    {
        $data = $this->metaDataLoader->getTableMeta($tableName)->prepareInsertData($data);
        $pData = $this->prepareInsertData($connection, $data);

        $sql = $this->getInsertSql($tableName, $pData[0], $pData[1])
            . ' RETURNING ' . $connection->quoteIdentifier($identifier)
        ;
        return $connection->fetchColumn($sql, $pData[2], 0);
    }

    public function update(Connection $connection, $tableName, array $data, array $identifier)
    {
        $data = array_diff_key($data, $identifier);
        $data = $this->metaDataLoader->getTableMeta($tableName)->prepareUpdateData($data);

        return parent::update($connection, $tableName, $data, $identifier);
    }

    protected function prepareParamValue($value)
    {
        if (\is_bool($value)) {
            // PostgreSQL PDO will accept a variety of string representations for boolean columns
            // including 't' and 'f'
            return $value ? 't' : 'f';
        } else {
            return parent::prepareParamValue($value);
        }
    }

    /**
     * Get table geom type
     *
     * @param string $tableName Table name. The name can contains schema name splited by dot.
     * @return string
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getTableGeomType($tableName)
    {
        $connection = $this->connection;
        if (strpos($tableName, '.')) {
            $parts = explode('.', $tableName, 2);
            $schema = $parts[0];
            $tableName = $parts[1];
        } else {
            $schema = null;
        }
        $sql = 'SELECT "type" FROM geometry_columns WHERE'
             . ' f_table_schema = ' . ($schema ? $connection->quote($schema) : 'current_schema()')
             . ' AND f_table_name = ' . $connection->quote($tableName)
        ;
        $type = $connection->query($sql)->fetchColumn();
        return $type;
    }

    public function getReadEwktSql($data)
    {
        return "ST_MakeValid(ST_GeomFromEWKT({$data}))";
    }

    public function getTransformSql($data, $sridTo)
    {
        if (!$sridTo || !\is_numeric($sridTo)) {
            throw new \InvalidArgumentException("Invalid sridTo " . print_r($sridTo, true));
        }
        return "ST_MakeValid(ST_Transform({$data}, " . intval($sridTo) . '))';
    }

    /**
     * @param string $geomExpression
     * @return string
     */
    public function getPromoteToCollectionSql($geomExpression)
    {
        return "ST_Multi({$geomExpression})";
    }

    public function getDumpWktSql($data)
    {
        return "ST_AsText({$data})";
    }

    /**
     * @inheritdoc
     */
    public function getIntersectCondition($wkt, $geomFieldName, $srid, $sridTo)
    {
        $db = $this->getConnection();
        $geomFieldName = $db->quoteIdentifier($geomFieldName);
        $wkt = $db->quote($wkt);
        $srid = is_numeric($srid) ? intval($srid) : $db->quote($srid);
        $sridTo = is_numeric($sridTo) ? intval($sridTo) : $db->quote($sridTo);
        return "(ST_TRANSFORM(ST_GEOMFROMTEXT($wkt,$srid),$sridTo) && $geomFieldName)";
    }

    /**
     * @inheritdoc
     */
    public function getGeomAttributeAsWkt($geometryAttribute, $sridTo)
    {
        $connection = $this->getConnection();
        $geomFieldName = $connection->quoteIdentifier($geometryAttribute);
        $sridTo = is_numeric($sridTo) ? intval($sridTo) : $connection->quote($sridTo);
        return "ST_ASTEXT(ST_TRANSFORM($geomFieldName, $sridTo)) AS $geomFieldName";
    }

    /**
     * @inheritdoc
     */
    public function findGeometryFieldSrid($tableName, $geomFieldName)
    {
        $connection = $this->getConnection();
        $sql = 'SELECT srid FROM "public"."geometry_columns" WHERE "f_geometry_column" = ? AND "f_table_name" = ?';
        $params[] = $geomFieldName;
        if (false !== strpos($tableName, ".")) {
            $tableNameParts = explode('.', $tableName, 2);
            $params[] = $tableNameParts[1];
            $params[] = $tableNameParts[0];
            $sql .= ' AND "f_table_schema" = ?';
        } else {
            $params[] = $tableName;
            $sql .= ' AND "f_table_schema" = current_schema()';
        }
        return $connection->fetchColumn($sql, $params);
    }

    public function getNodeFromGeom($waysVerticesTableName, $waysGeomFieldName, $ewkt, $transformTo = null, $idKey = "id")
    {
        return LegacyPgRouting::nodeFromGeom($this->getConnection(), $waysVerticesTableName, $waysGeomFieldName, $ewkt, $transformTo, $idKey);
    }

    public function routeBetweenNodes($waysTableName, $waysGeomFieldName, $startNodeId, $endNodeId, $srid, $directedGraph = false, $hasReverseCost = false)
    {
        $results = LegacyPgRouting::route($this->getConnection(), $waysTableName, $waysGeomFieldName, $startNodeId, $endNodeId, $srid, $directedGraph, $hasReverseCost);
        $features = array();
        $geomName = 'geom'; // This is hard-coded in the routing query sql
        $idName = 'orderId'; // This is hard-coded in the routing query sql
        foreach ($results as $row) {
            $feature = new Feature(array(), $srid, $idName, $geomName);
            $feature->setAttributes($row);
            $features[] = $feature;
        }
        return $features;
    }
}
