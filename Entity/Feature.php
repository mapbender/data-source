<?php
namespace Mapbender\DataSourceBundle\Entity;

use Mapbender\DataSourceBundle\Utils\WktUtility;

class Feature extends DataItem
{
    protected ?string $geomField;

    public function __construct(array $attributes = [], string $uniqueIdField = 'id', string $geomField = "geom")
    {
        $this->geomField = $geomField;
        $attributes[$geomField] = $attributes[$geomField] ?? null;
        parent::__construct($attributes, $uniqueIdField);
    }

    public function setGeom(?string $geom): self
    {
        if ($geom) {
            $newSrid = WktUtility::getEwktSrid($geom) ?: $this->getSrid();
            if ($newSrid) {
                $geom = "SRID={$newSrid};$geom";
            }
        }
        $this->attributes[$this->geomField] = $geom;

        return $this;
    }

    public function getGeom(): ?string
    {
        return WktUtility::wktFromEwkt($this->attributes[$this->geomField]);
    }

    public function getEwkt(): ?string
    {
        return $this->attributes[$this->geomField];
    }

    public function getSrid(): ?int
    {
        return WktUtility::getEwktSrid($this->getEwkt());
    }

    public function setSrid(int $srid): void
    {
        if ($wkt = WktUtility::wktFromEwkt($this->attributes[$this->geomField])) {
            $this->attributes[$this->geomField] = "SRID={$srid};{$wkt}";
        }
    }

    public function setAttributes(array $attributes): void
    {
        if (array_key_exists($this->geomField, $attributes)) {
            $this->setGeom($attributes[$this->geomField]);
            unset($attributes[$this->geomField]);
        }
        parent::setAttributes($attributes);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if ($key === $this->geomField) {
            $this->setGeom($value);
        } else {
            parent::setAttribute($key, $value);
        }
    }

    public function getType(): ?string
    {
        return WktUtility::getGeometryType($this->attributes[$this->geomField]);
    }
}
