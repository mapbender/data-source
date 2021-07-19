<?php


namespace Mapbender\DataSourceBundle\Component\Meta;


class TableMeta
{
    /** @var Column[] */
    protected $columns;
    /** @var string[] */
    protected $columnAliases = array();

    /**
     * @param Column[] $columns
     * @param string[] $columnAliases
     */
    public function __construct(array $columns, $columnAliases = array())
    {
        $this->columns = $columns;
        foreach ($columnAliases as $a => $b) {
            $this->columnAliases[$a] = $b;
            $this->columnAliases[$b] = $a;
        }
    }

    public function prepareUpdateData(array $data)
    {
        foreach ($data as $columnName => $value) {
            if (\is_string($value) && !$value) {
                $column = $this->getColumn($columnName);
                if ($column->isNumeric()) {
                    $data[$columnName] = $column->getSafeDefault();
                }
            }
        }
        return $data;
    }

    public function prepareInsertData(array $data)
    {
        $data = $this->prepareUpdateData($data);
        $columnNames = \array_keys($this->columns);
        foreach ($columnNames as $columnName) {
            $column = $this->getColumn($columnName);
            if (!$column->hasDefault()) {
                if (!\array_key_exists($columnName, $data)) {
                    $data[$columnName] = $column->getSafeDefault();
                }
            }
        }
        return $data;
    }

    /**
     * @param string $name
     * @return Column
     * @throws \RuntimeException
     */
    protected function getColumn($name)
    {
        foreach ($this->getAliases($name, true) as $alias) {
            if (\array_key_exists($alias, $this->columns)) {
                return $this->columns[$alias];
            }
        }
        throw new \RuntimeException("Unknown column {$name}");
    }

    /**
     * @param string $columnName
     * @param boolean $includeOriginal
     * @return string[]
     */
    protected function getAliases($columnName, $includeOriginal)
    {
        $names = array();
        if ($includeOriginal) {
            $names[] = $columnName;
        }
        while (!empty($this->columnAliases[$columnName])) {
            $alias = $this->columnAliases[$columnName];
            if (\in_array($alias, $names)) {
                break;
            } else {
                $names[] = $alias;
                $columnName = $alias;
            }
        }
        return $names;
    }
}
