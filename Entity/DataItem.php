<?php
namespace Mapbender\DataSourceBundle\Entity;

/**
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 */
class DataItem
{
    /** @var mixed[] */
    protected $attributes = array();

    /** @var string */
    protected $uniqueIdField;

    /** @var DataItem[]|null */
    protected $children;

    /**
     * @param mixed  $args string|array|null Optional JSON string or array
     * @param string $uniqueIdField ID field name
     * @internal
     */
    public function __construct(array $args = array(), $uniqueIdField = 'id')
    {
        $this->uniqueIdField = $uniqueIdField;
        if (!array_key_exists($this->uniqueIdField, $args)) {
            // ensure getId works
            $args[$this->uniqueIdField] = null;
        }
        $this->setAttributes($args);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->attributes[$this->uniqueIdField] = $id;
    }

    /**
     * Is id not null
     *
     * @return bool
     * @deprecated use getId and coerce to boolean
     */
    public function hasId()
    {
        return !is_null($this->getId());
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->attributes[$this->uniqueIdField];
    }

    /**
     * Get attributes
     *
     * @return mixed[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        return $this->attributes[$name];
    }

    /**
     * ADD attributes
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
     * @deprecated
     * @todo 0.2: remove this method
     */
    public function setChildren($children)
    {
        $this->children = $children;
    }

    /**
     * @return DataItem[]|null
     * @deprecated
     * @todo 0.2: remove this method
     */
    public function getChildren()
    {
        return $this->children;
    }
}
