<?php
namespace Mapbender\DataSourceBundle\Entity;

use Mapbender\DataSourceBundle\Utils\WktUtility;

/**
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 */
class Feature extends DataItem
{
     /* @var string|null in wkt format */
    protected $geom;

    /** @var string|null */
    protected $geomField;

    /**
     * @param string|null $geom
     * @return $this
     */
    public function setGeom($geom)
    {
        if ($geom && !($newSrid = WktUtility::getEwktSrid($geom))) {
            if ($oldSrid = $this->getSrid()) {
                $geom = "SRID={$oldSrid};$geom";
            }
        }
        $this->geom = $geom ?: null;

        return $this;
    }

    /**
     * Get geometry as WKT.
     * @return string|null
     */
    public function getGeom()
    {
        return WktUtility::wktFromEwkt($this->geom);
    }

    /**
     * Get geometry as EWKT string.
     *
     * @return string|null
     */
    public function getEwkt()
    {
        return $this->geom ?: null;
    }

    /**
     * @return integer|null
     */
    public function getSrid()
    {
        return WktUtility::getEwktSrid($this->geom);
    }

    /**
     * @param integer $srid
     */
    public function setSrid($srid)
    {
        if ($wkt = WktUtility::wktFromEwkt($this->geom)) {
            $this->geom = "SRID={$srid};{$wkt}";
        }
    }

    /**
     * @param array $args
     * @param int $srid
     * @param string $uniqueIdField
     * @param string $geomField
     * @internal
     */
    public function __construct(array $args = array(), $srid = null, $uniqueIdField = 'id', $geomField = "geom")
    {
        $this->geomField = $geomField;
        parent::__construct($args, $uniqueIdField);
    }

    /**
     * Get GeoJSON
     *
     * @return array in GeoJSON format
     * @throws \Exception
     */
    public function toGeoJson()
    {
        $wkt = $this->getGeom();
        if ($wkt) {
            /**
             * Encode to array format; @see \GeoJSON::write
             */
            $wkt = \geoPHP::load($wkt, 'wkt')->out('json', true);
        }

        return array('type'       => 'Feature',
                     'properties' => $this->getAttributes(),
                     'geometry'   => $wkt,
                     'id'         => $this->getId(),
                     'srid'       => $this->getSrid());
    }

    /**
     * Return GeoJSON string
     *
     * @return string
     * @deprecated too much magic; if you want GeoJSON, call toGeoJson and json_encode explicitly
     * @todo: remove method
     */
    public function __toString()
    {
        @trigger_error("Magic Feature::__toString invocation is deprecated and will be removed in 0.2.0; call toGeoJson and perfom json_encode explicitly", E_USER_DEPRECATED);
        return json_encode($this->toGeoJson());
    }

    /**
     * Return array
     *
     * @return mixed
     */
    public function toArray()
    {
        $data = parent::toArray();
        $data[$this->geomField] = $this->getEwkt();
        return $data;
    }

    /**
     * ADD attributes
     *
     * @param mixed $attributes
     */
    public function setAttributes($attributes)
    {
        if (array_key_exists($this->geomField, $attributes)) {
            $this->setGeom($attributes[$this->geomField]);
            unset($attributes[$this->geomField]);
        }
        parent::setAttributes($attributes);
    }

    public function setAttribute($key, $value)
    {
        if ($key === $this->geomField) {
            $this->setGeom($value);
        } else {
            parent::setAttribute($key, $value);
        }
    }

    /**
     * Get geometry type
     *
     * @return string|null
     */
    public function getType()
    {
        return WktUtility::getGeometryType($this->geom);
    }

}
