<?php
namespace Mapbender\DataSourceBundle\Entity;

class DataItem implements \ArrayAccess
{
    protected array $attributes = [];
    protected string $uniqueIdField;

    public function __construct(array $attributes = [], string $uniqueIdField = 'id')
    {
        $this->uniqueIdField = $uniqueIdField;
        $attributes[$this->uniqueIdField] = $attributes[$this->uniqueIdField] ?? null;
        $this->setAttributes($attributes);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function setId(mixed $id): void
    {
        $this->attributes[$this->uniqueIdField] = $id;
    }

    public function hasId(): bool
    {
        return $this->getId() !== null;
    }

    public function getId(): ?int
    {
        return $this->attributes[$this->uniqueIdField];
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = array_merge($this->attributes, $attributes);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->attributes);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
