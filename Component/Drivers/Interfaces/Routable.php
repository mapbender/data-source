<?php
namespace Mapbender\DataSourceBundle\Component\Drivers\Interfaces;

use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * @deprecated we already have a routing bundle that does much more, much better
 */
interface Routable
{
    /**
     * Get id of geometry in given table nearest to given ewkt
     *
     * @param string $waysVerticesTableName
     * @param string $waysGeomFieldName
     * @param string $ewkt
     * @param null|int $transformTo optional srid
     * @param string $idKey
     * @return mixed id column value
     * @todo: this has nothing to do with routing
     * @todo: support returning more than just the id
     */
    public function getNodeFromGeom(
        $waysVerticesTableName,
        $waysGeomFieldName,
        $ewkt,
        $transformTo = null,
        $idKey = "id"
    );

    /**
     * @param string $waysTableName
     * @param string $waysGeomFieldName
     * @param int $startNodeId
     * @param int $endNodeId
     * @param mixed $srid completely ignored @todo: either use this argument or remove it
     * @param bool $directedGraph directed graph
     * @param bool $hasReverseCost Has reverse cost, only can be true, if  directed graph=true
     * @return DataItem[]
     */
    public function routeBetweenNodes(
        $waysTableName,
        $waysGeomFieldName,
        $startNodeId,
        $endNodeId,
        $srid,
        $directedGraph = false,
        $hasReverseCost = false
    );
}
