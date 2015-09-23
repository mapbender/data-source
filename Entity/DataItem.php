<?php
namespace Mapbender\DataSourceBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class DataSource
 *
 * @package   Mapbender\CoreBundle\Entity
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 */
class DataItem
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    protected $id;

    /**
     * @var
     */
    protected $attributes;

    /** @var string */
    private $uniqueIdField;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get attributes (parameters)
     *
     * @return mixed
     */
    public function getAttributes()
    {
        $this->attributes[$this->uniqueIdField] = $this->getId();
        return $this->attributes;
    }

    /**
     * @param mixed $attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * TODO: implement
     *
     * @return DataItem
     */
    public function getParent()
    {
        return new DataItem();
    }

    /**
     * TODO: implement
     *
     * @param DataItem $dataItem
     * @return DataItem
     */
    public function setParent(DataItem $dataItem = null)
    {
        return $dataItem;
    }

    /**
     * @param mixed  $args          JSON or array(
     * @param int    $srid
     * @param string $uniqueIdField ID field name
     * @param string $geomField     GEOM field name
     */
    public function __construct($args = null, $srid = null, $uniqueIdField = 'id', $geomField = "geom")
    {
        // Is JSON DataSource array?
        if (is_array($args) && isset($args['properties'])) {
            $properties = $args["properties"];

            if (isset($args['id'])) {
                $properties[$uniqueIdField] = $args['id'];
            }

            $args = $properties;
        }

        // set ID
        if (isset($args[$uniqueIdField])) {
            $this->setId($args[$uniqueIdField]);
            unset($args[$uniqueIdField]);
        }

        // set attributes
        $this->setAttributes($args);

        $this->uniqueIdField = $uniqueIdField;
    }

    /**
     * Return GeoJSON string
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this);
    }

    /**
     * Return array
     *
     * @return mixed
     */
    public function toArray()
    {
        $data = $this->getAttributes();

        if (!$this->hasId()) {
            unset($data[$this->uniqueIdField]);
        } else {
            $data[$this->uniqueIdField] = $this->getId();
        }

        return $data;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Is id set
     *
     * @return bool
     */
    public function hasId()
    {
        return !is_null($this->id);
    }
}