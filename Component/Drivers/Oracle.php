<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class Oracle extends DoctrineBaseDriver implements Geographic
{
    /**
     * Transform result column names from lower case to upper
     *
     * @param array[] $rows
     */
    public static function transformColumnNames(&$rows)
    {
        if (!$rows) {
            $columnNames = array();
        } else {
            $columnNames = array_keys(current($rows));
        }
        foreach ($rows as &$row) {
            foreach ($columnNames as $name) {
                $row[ strtolower($name) ] = &$row[ $name ];
                unset($row[ $name ]);
            }
        }
    }

    /**
     * Add geometry column
     *
     * @param string $tableName
     * @param string $type
     * @param integer $srid
     * @param string $geomFieldName
     * @param string $schemaName
     * @param int    $dimensions
     * @return mixed
     * @deprecated remove in 0.2.0 this is DBA work
     */
    public function addGeometryColumn($tableName,
        $type,
        $srid,
        $geomFieldName = "geom",
        $schemaName = "public",
        $dimensions = 2)
    {
        throw new \RuntimeException("Method not implemented");
    }

    /**
     * Get table geometry type
     *
     * @param string $tableName
     * @param string $schema
     * @return mixed
     */
    public function getTableGeomType($tableName, $schema = null)
    {
        // TODO: Implement getTableGeomType() method.
        throw new \RuntimeException("Method not implemented");
    }

    public function getReadEwktSql($data)
    {
        return "SDO_UTIL.TO_WKBGEOMETRY({$data})";
    }

    public function getTransformSql($data, $sridTo)
    {
        if (!$sridTo || !\is_numeric($sridTo)) {
            throw new \InvalidArgumentException("Invalid sridTo " . print_r($sridTo, true));
        }
        return "SDO_CS.TRANSFORM({$data}, " . intval($sridTo) . ')';
    }

    public function getDumpWktSql($data)
    {
        return "SDO_UTIL.TO_WKTGEOMETRY({$data})";
    }

    /**
     * @param string $geomExpression
     * @return string
     */
    public function getPromoteToCollectionSql($geomExpression)
    {
        // no implementation
        // @todo: support this? Use cases?
        return $geomExpression;
    }

    /**
     * @inheritdoc
     */
    public function getIntersectCondition($wkt, $geomFieldName, $srid, $sridTo)
    {
        return "SDO_RELATE($geomFieldName, SDO_GEOMETRY('$wkt', $srid), 'mask=ANYINTERACT querytype=WINDOW') = 'TRUE'";
    }

    /**
     * @inheritdoc
     */
    public function getGeomAttributeAsWkt($geometryAttribute, $sridTo)
    {
      return "SDO_UTIL.TO_WKTGEOMETRY(SDO_CS.TRANSFORM($geometryAttribute, $sridTo)) AS $geometryAttribute";
    }

    /**
     * @inheritdoc
     */
    public function findGeometryFieldSrid($tableName, $geomFieldName)
    {
        $connection = $this->getConnection();
        return $connection->fetchColumn("SELECT {$tableName}.{$geomFieldName}.SDO_SRID FROM TABLE " . $tableName);
    }

    protected function loadColumnsMetaData($table)
    {
        throw new \RuntimeException("Metadata loading not implemented in " . \get_class($this));
    }
}
