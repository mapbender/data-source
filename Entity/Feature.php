<?php
namespace Mapbender\DataSourceBundle\Entity;

/**
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 */
class Feature extends DataItem
{
    const TYPE_POINT              = 'POINT';
    const TYPE_LINESTRING         = 'LINESTRING';
    const TYPE_POLYGON            = 'POLYGON';
    const TYPE_MULTIPOINT         = 'MULTIPOINT';
    const TYPE_MULTILINESTRING    = 'MULTILINESTRING';
    const TYPE_MULTIPOLYGON       = 'MULTIPOLYGON';
    const TYPE_GEOMETRYCOLLECTION = 'GEOMETRYCOLLECTION';

    static public $simpleGeometries = array(
        Feature::TYPE_POINT,
        Feature::TYPE_LINESTRING,
        Feature::TYPE_POLYGON,
    );

    static public $complexGeometries = array(
        Feature::TYPE_MULTIPOLYGON,
        Feature::TYPE_MULTILINESTRING,
        Feature::TYPE_MULTIPOINT
    );

     /* @var string|null in wkt format */
    protected $geom;

    /** @var integer|null */
    protected $srid;

    /** @var string|null */
    protected $geomField;

    /**
     * Geometry type.
     * @var string|null
     */
    protected $type;

    /**
     * @param string|null $geom
     * @return $this
     */
    public function setGeom($geom)
    {
        $this->geom = $geom;
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
     * @param mixed $args JSON or array(
     * @param int $srid
     * @param string $uniqueIdField ID field name
     * @param string $geomField GEOM field name
     */
    public function __construct($args = null, $srid = null, $uniqueIdField = 'id', $geomField = "geom")
    {
        $this->geomField = $geomField;

        // decode JSON
        if (is_string($args)) {
            $args = json_decode($args, true);
            if (isset($args["geometry"])) {
                $args["geom"] = \geoPHP::load($args["geometry"], 'json')->out('wkt');
            }
        }

        $this->setSrid($srid);

        // Is JSON feature array?
        if (is_array($args) && isset($args["geometry"]) && isset($args['properties'])) {
            $properties             = $args["properties"];
            $geom                   = $args["geometry"];
            $properties[$geomField] = $geom;

            if (isset($args['id'])) {
                $properties[$uniqueIdField] = $args['id'];
            }

            if (isset($args['srid'])) {
                $this->setSrid($args['srid']);
            }

            $args = $properties;
        }

        // set GEOM
        if (isset($args[$geomField])) {
            $this->setGeom($args[$geomField]);
            unset($args[$geomField]);
        }

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
        $data = $this->getAttributes();

        if ($this->hasGeom() && $this->getSrid()) {
            $data[$this->geomField] = "SRID=" . $this->getSrid() . ";" . $this->getGeom();
        }

        if (!$this->hasId()) {
            unset($data[$this->uniqueIdField]);
        }else{
            $data[$this->uniqueIdField] = $this->getId();
        }

        return $data;
    }

    /**
     * Has geom data
     *
     * @return bool
     */
    public function hasGeom(){
        return !is_null($this->geom);
    }

    /**
     * Get geometry type
     *
     * TODO: recover type from geometry.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set geometry type
     *
     * @param string $type Feature::TYPE_*
     */
    public function setType($type)
    {
        $this->type = $type;
    }
}
