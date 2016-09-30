<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Geographic;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class PostgreSQL
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class Oracle extends DoctrineBaseDriver implements Geographic
{
    /**
     * Transform result column names from lower case to upper
     *
     * @param        $rows         array Two dimensional array link
     */
    public static function transformColumnNames(&$rows)
    {
        $columnNames = array_keys(current($rows));
        foreach ($rows as &$row) {
            foreach ($columnNames as $name) {
                $row[ strtolower($name) ] = &$row[ $name ];
                unset($row[ $name ]);
            }
        }
    }

    /**
     * Convert results to Feature objects
     *
     * @param DataItem[] $rows
     * @param null       $srid
     * @return DataItem[]
     */
    public function prepareResults(&$rows, $srid = null)
    {
        // Transform Oracle result column names from upper to lower case
        self::transformColumnNames($rows);

        foreach ($rows as $key => &$row) {
            $row = $this->create($row, $srid);
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
        // TODO: Implement addGeometryColumn() method.
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
        // TODO: Implement getTableGeomType() method.
    }

    /**
     * @param      $ewkt
     * @param null $srid
     * @return mixed
     * @internal param $wkt
     */
    public function transformEwkt($ewkt, $srid = null)
    {
        return $this->getConnection()->fetchColumn(
        /** @lang Oracle */
            "SELECT 
              SDO_CS.TRANSFORM(
                SDO_UTIL.TO_WKBGEOMETRY('$ewkt'), 
              $srid)");
    }
}