<?php
namespace Mapbender\DataSourceBundle\Component\Drivers\Interfaces;

/**
 * Interface Geographic
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 */
interface Geographic
{
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
        $dimensions = 2);

    /**
     * Get table geometry type
     *
     * @param        $tableName
     * @param string $schema
     * @return mixed
     */
    public function getTableGeomType($tableName, $schema = null);

    /**
     * @param      $ewkt
     * @param null $srid
     * @return mixed
     * @internal param $wkt
     */
    public function transformEwkt($ewkt, $srid = null);

    /**
     * Get intersect SQL condition
     *
     * @param string $wkt           WKT
     * @param string $geomFieldName geometry field name
     * @param string $srid          SRID convert from
     * @param string $sridTo        SRID convert to
     * @return string SQL
     */
    public function getIntersectCondition($wkt, $geomFieldName, $srid, $sridTo);

    /**
     * Get WKB geometry attribute as WKT
     *
     * @param string $geometryAttribute
     * @param string $sridTo SRID convert to
     * @return string SQL
     */
    public function getGeomAttributeAsWkt($geometryAttribute, $sridTo);


    /**
     * Get WKB geometry attribute as WKT
     *
     * @param  string $tableName
     * @param  string $geomFieldName
     * @return string SQL
     * @internal param $this
     */
    public function findGeometryFieldSrid($tableName, $geomFieldName);
}

