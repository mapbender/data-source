<?php


namespace Mapbender\DataSourceBundle\Component\Meta;


class Column
{
    /** @var bool */
    protected $nullable;
    /** @var bool */
    protected $hasDefault;
    /** @var bool */
    protected $isNumeric;

    /**
     * @param boolean $nullable
     * @param boolean $hasDefault
     * @param boolean $isNumeric
     */
    public function __construct($nullable, $hasDefault, $isNumeric)
    {
        $this->nullable = $nullable;
        $this->hasDefault = $hasDefault;
        $this->isNumeric = $isNumeric;
    }

    /**
     * @return int|string|null
     */
    public function getSafeDefault()
    {
        if ($this->nullable) {
            return null;
        } elseif ($this->isNumeric) {
            return 0;
        } else {
            return '';
        }
    }

    /**
     * @return bool
     */
    public function isNullable()
    {
        return $this->nullable;
    }

    /**
     * @return bool
     */
    public function hasDefault()
    {
        return $this->hasDefault;
    }

    /**
     * @return bool
     */
    public function isNumeric()
    {
        return $this->isNumeric;
    }
}
