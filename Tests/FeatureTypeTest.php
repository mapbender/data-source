<?php
namespace Mapbender\DataSourceBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Mapbender\DataSourceBundle\Component\FeatureType;
use Mapbender\DataSourceBundle\Entity\Feature;

/**
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class FeatureTypeTest extends SymfonyTest
{
    const REMOVE_TEST_TABLES = false;

    protected $configuration = array();

    // The OGC and ISO specifications
    const WKT_POINT              = "POINT(0 0)";
    const WKT_LINESTRING         = "LINESTRING(0 0,1 1,1 2)";
    const WKT_POLYGON            = "POLYGON((0 0,4 0,4 4,0 4,0 0),(1 1, 2 1, 2 2, 1 2,1 1))";
    const WKT_MULTIPOINT         = "MULTIPOINT(0 0,1 2)";
    const WKT_MULTILINESTRING    = "MULTILINESTRING((0 0,1 1,1 2),(2 3,3 2,5 4))";
    const WKT_MULTIPOLYGON       = "MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0),(1 1,2 1,2 2,1 2,1 1)), ((-1 -1,-1 -2,-2 -2,-2 -1,-1 -1)))";
    const WKT_GEOMETRYCOLLECTION = "GEOMETRYCOLLECTION(POINT(2 3),LINESTRING(2 3,3 4))";

    // PostGIS extended specifications
    const WKT_MULTIPOINTM         = "MULTIPOINTM(0 0 0,1 2 1)";
    const WKT_GEOMETRYCOLLECTIONM = "GEOMETRYCOLLECTIONM( POINTM(2 3 9), LINESTRINGM(2 3 4, 3 4 5) )";
    const WKT_MULTICURVE          = "MULTICURVE( (0 0, 5 5), CIRCULARSTRING(4 0, 4 4, 8 4) )( (0 0, 5 5), CIRCULARSTRING(4 0, 4 4, 8 4) )";
    const WKT_POLYHEDRALSURFACE   = "POLYHEDRALSURFACE( ((0 0 0, 0 0 1, 0 1 1, 0 1 0, 0 0 0)), ((0 0 0, 0 1 0, 1 1 0, 1 0 0, 0 0 0)), ((0 0 0, 1 0 0, 1 0 1, 0 0 1, 0
0 0)), ((1 1 0, 1 1 1, 1 0 1, 1 0 0, 1 1 0)), ((0 1 0, 0 1 1, 1 1 1, 1 1 0, 0 1 0)), ((0 0 1, 1 0 1, 1 1 1, 0 1 1, 0 0 1)) )";
    const WKT_TRIANGLE            = "TRIANGLE ((0 0, 0 9, 9 0, 0 0))";
    const WKT_TIN                 = "TIN( ((0 0 0, 0 0 1, 0 1 0, 0 0 0)), ((0 0 0, 0 1 0, 1 1 0, 0 0 0)) )";
    const WKT_CIRCULARSTRING      = "CIRCULARSTRING(0 0, 1 1, 1 0)";
    const WKT_COMPOUNDCURVE       = "COMPOUNDCURVE(CIRCULARSTRING(0 0, 1 1, 1 0),(1 0, 0 1))";
    const WKT_CURVEPOLYGON        = "CURVEPOLYGON(CIRCULARSTRING(0 0, 4 0, 4 4, 0 4, 0 0),(1 1, 3 3, 3 1, 1 1))";
    const WKT_MULTISURFACE        = "MULTISURFACE(CURVEPOLYGON(CIRCULARSTRING(0 0, 4 0, 4 4, 0 4, 0 0),(1 1, 3 3, 3 1, 1 1)),((10 10, 14 12, 11 10,
10 10),(11 11, 11.5 11, 11 11.5, 11 11)))";


    /**
     * Test save and recognize geometries
     */
    public function testGeometries()
    {
        // @todo: use a fixture for read / write database tests
        /** @var Registry $doctrine */
        $container      = self::$container;
        $doctrine       = $container->get("doctrine");
        $container->get("features");
        $connectionName = $this->configuration['connection'];
        $doctrine->getConnection($connectionName);

        foreach (array(
                     self::WKT_POINT,
                     self::WKT_POLYGON,
                     self::WKT_LINESTRING,
                     self::WKT_MULTIPOINT,
                     self::WKT_MULTILINESTRING,
                     self::WKT_MULTIPOLYGON,
                     self::WKT_GEOMETRYCOLLECTION,
                 ) as $wkt) {

            $type          = FeatureType::getWktType($wkt);
            $wkt           = preg_replace('/,\s+/s', ',', $wkt);
            $tableName     = "test_" . strtolower($type);
            $srid          = 4326;
            $geomFieldName = 'geom';
            $uniqueIdField = 'id';
            $featureType   = new FeatureType($container, array(
                'connection' => $connectionName,
                'table'      => $tableName,
                'srid'       => $srid,
                'geomField'  => $geomFieldName
            ));
            $driver        = $featureType->getDriver();
            $feature       = new Feature(array(
                'geometry'   => $wkt,
                'properties' => array()
            ), $srid, $uniqueIdField, $geomFieldName);

            $driver->createTable($tableName, $uniqueIdField, true);
            $featureType->addGeometryColumn($tableName, $type, $srid, $geomFieldName);

            for ($i = 0; $i < 10; $i++) {
                $savedFeature = $featureType->save($feature);
                $feature->setId(null);
                $this->assertEquals($savedFeature->getGeom(), $wkt);
            }

            if(self::REMOVE_TEST_TABLES){
                $driver->dropTable($tableName);
            }
        }
    }
}
