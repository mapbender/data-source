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

    /** @var integer|null */
    protected $srid;

    /** @var string|null */
    protected $geomField;

    /**
     * @param string|null $geom
     * @return $this
     */
    public function setGeom($geom)
    {
        if ($geom && $srid = WktUtility::getEwktSrid($geom)) {
            $this->geom = WktUtility::wktFromEwkt($geom) ?: null;
            $this->setSrid($srid);
        } else {
            $this->geom = $geom ?: null;
        }

        return $this;
    }

    /**
     * Get geometry as WKT.
     * @return string|null
     */
    public function getGeom()
    {
        return $this->geom;
    }

    /**
     * Get geometry as EWKT string.
     *
     * @return string|null
     */
    public function getEwkt()
    {
        $geom = $this->getGeom();
        $srid = $this->getSrid();
        if ($geom && $srid) {
            return "SRID={$srid};{$geom}";
        } else {
            return null;
        }
    }

    /**
     * @return integer|null
     */
    public function getSrid()
    {
        return $this->srid;
    }

    /**
     * @param integer $srid
     */
    public function setSrid($srid)
    {
        $this->srid = intval($srid);
    }

    /**
     * @return bool
     */
    public function hasSrid()
    {
        return !!$this->srid;
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
        $this->setSrid($srid);
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
     * Has geom data
     *
     * @return bool
     * @deprecated coerce to boolean
     * @todo 0.2.0: remove this method
     */
    public function hasGeom(){
        return !is_null($this->geom);
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
