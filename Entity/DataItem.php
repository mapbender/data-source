<?php
namespace Mapbender\DataSourceBundle\Entity;

/**
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 */
class DataItem
{
    /** @var integer */
    protected $id;

    /** @var mixed[] */
    protected $attributes = array();

    /** @var string */
    protected $uniqueIdField;

    /** @var DataItem[]|null */
    protected $children;

    /**
     * @param mixed  $args string|array|null Optional JSON string or array
     * @param string $uniqueIdField ID field name
     * @param bool   $fill array|null Fill array
     */
    public function __construct($args = null, $uniqueIdField = 'id', $fill = false)
    {
        $this->uniqueIdField = $uniqueIdField;

        // decode JSON
        if (is_string($args)) {
            $args = json_decode($args, true);
        }

        // Is JSON DataSource array?
        if ($fill && is_array($args) && isset($args['attributes'])) {
            $attributes = $args["attributes"];

            if (isset($args['id'])) {
                $attributes[$uniqueIdField] = $args['id'];
            }

            $args = $attributes;
        }

        // set ID
        if (isset($args[$uniqueIdField])) {
            $this->setId($args[$uniqueIdField]);
            unset($args[$uniqueIdField]);
        }

        // set attributes
        $this->setAttributes($args);
    }

    /**
     * Return string
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

        if($children = $this->getChildren()){
           $_children = array();
            foreach($children as $child){
               $_children[] = $child->toArray();
           }
            $data["children"] = &$_children;
        }

        return $data;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
        $this->attributes[$this->uniqueIdField] = $id;
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
     * Get attributes
     *
     * @return mixed[]
     */
    public function getAttributes()
    {
        $this->attributes[$this->uniqueIdField] = $this->getId();
        return $this->attributes;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttribute($name){
        $attributes = $this->getAttributes();
        return $attributes[$name];
    }

    /**
     * Merge attributes
     *
     * @param mixed $attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);
    }

    /**
     * Set attribute
     *
     * @param string $key
     * @param mixed $value
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[ $key ] = $value;
    }

    /**
     * @param DataItem[] $children
     */
    public function setChildren($children)
    {
        $this->children = $children;
    }

    /**
     * @return DataItem[]|null
     */
    public function getChildren()
    {
        return $this->children;
    }
}
