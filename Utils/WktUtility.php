<?php


namespace Mapbender\DataSourceBundle\Utils;


class WktUtility
{
    public static function getGeometryType($wkt)
    {
        // Reduce EWKT to wkt
        $wkt = preg_replace('#^SRID=[^\;]*;#', '', $wkt);
        $matches = array();
        if (preg_match('#^\w+#', $wkt, $matches)) {
            return $matches[0];
        } else {
            return null;
        }
    }
}
