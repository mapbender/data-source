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
    public function __construct($args = array(), $uniqueIdField = 'id')
    {
        $this->uniqueIdField = $uniqueIdField;

        if (!is_array($args)) {
            @trigger_error('DEPRECATED: initializing ' . get_class($this) . ' with type ' . gettype($args) . ' $args is is deprecated. Pass an array.', E_USER_DEPRECATED);
            if (is_string($args)) {
                $newArgs = json_decode($args, true);
                if ($newArgs === null && $args !== json_encode('null')) {
                    throw new \InvalidArgumentException("Json decode failure for " . print_r($args, true));
                }
                if ($newArgs === null) {
                    $args = array();
                } else {
                    $args = $newArgs;
                }
                if (!is_array($args)) {
                    throw new \InvalidArgumentException('Invalid $args type ' . gettype($args) . ' post-decode. Expected array.');
                }
            }
        }

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
        $data = $this->getAttributes();

        // @todo: Emit everything, including id
        //        The only reason this might break anything is if some JavaScript code
        //        checks for a populated id via .hasOwnProperty instead of using simple
        //        boolean coersion.
        if (!$this->hasId()) {
            unset($data[$this->uniqueIdField]);
        }

        if ($children = $this->getChildren()) {
            $data['children'] = array();
            foreach ($children as $child) {
                $data['children'][] = $child->toArray();
            }
        }

        return $data;
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
    public function getAttribute($name){
        $attributes = $this->getAttributes();
        return $attributes[$name];
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
