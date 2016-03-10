<?php

namespace Mapbender\DataSourceBundle\Component\Drivers;

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
}